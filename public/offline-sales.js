(() => {
    const databaseName = 'thai2d3d-offline-sales';
    const databaseVersion = 1;
    const statusNode = document.getElementById('offline-status') || document.getElementById('sale-entry-status');
    const listNode = document.getElementById('offline-queue-list');
    const offlineSessionId = document.querySelector('meta[name="offline-session-id"]')?.content;
    const syncUrl = document.querySelector('meta[name="offline-sync-url"]')?.content;
    const t = window.offlineLabels;
    const fmt = (text, vars = {}) => text.replace(/:(\w+)/g,
        (token, key) => Object.prototype.hasOwnProperty.call(vars, key) ? String(vars[key]) : token);

    const openDatabase = () => new Promise((resolve, reject) => {
        if (!('indexedDB' in window)) {
            reject(new Error(t.storage_unsupported));
            return;
        }
        const request = indexedDB.open(databaseName, databaseVersion);
        request.onupgradeneeded = () => {
            const database = request.result;
            if (!database.objectStoreNames.contains('sales')) {
                database.createObjectStore('sales', { keyPath: 'client_uuid' });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error || new Error(t.storage_open_failed));
    });

    const storeRecords = async (mode, operation) => {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction('sales', mode);
            const request = operation(transaction.objectStore('sales'));
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error || new Error(t.storage_op_failed));
            transaction.oncomplete = () => database.close();
            transaction.onerror = () => {
                database.close();
                reject(transaction.error || new Error(t.storage_tx_failed));
            };
        });
    };

    const getAllRecords = () => storeRecords('readonly', store => store.getAll());
    const saveRecord = record => storeRecords('readwrite', store => store.put(record));
    const removeRecord = uuid => storeRecords('readwrite', store => store.delete(uuid));

    const createUuid = () => {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();

        const bytes = new Uint8Array(16);
        if (window.crypto?.getRandomValues) {
            window.crypto.getRandomValues(bytes);
        } else {
            for (let index = 0; index < bytes.length; index++) bytes[index] = Math.random() * 256;
        }
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };

    const showStatus = (message, isError = false) => {
        if (!statusNode) return;
        statusNode.hidden = false;
        statusNode.textContent = message;
        statusNode.classList.toggle('error', isError);
    };

    const refreshQueue = async () => {
        if (!listNode) return;
        const records = await getAllRecords();
        const sessionRecords = offlineSessionId
            ? records.filter(record => String(record.session_id) === offlineSessionId)
            : records;
        listNode.replaceChildren();
        if (sessionRecords.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'muted';
            empty.textContent = t.queue_empty;
            listNode.append(empty);
            return;
        }
        for (const record of sessionRecords) {
            const row = document.createElement('div');
            row.className = 'queue-item';
            const label = document.createElement('strong');
            label.textContent = `${record.sale_type.toUpperCase()} · ${record.input}`;
            const detail = document.createElement('span');
            detail.className = 'muted';
            detail.textContent = ` · ${record.state === 'queued' || !record.state ? t.state_queued : record.state}${record.error ? ` · ${record.error}` : ''}`;
            row.append(label, detail);
            listNode.append(row);
        }
    };

    const createRecord = (sessionId, saleType, input) => ({
        client_uuid: createUuid(),
        session_id: Number(sessionId),
        sale_type: saleType,
        input,
        recorded_at: new Date().toISOString(),
        state: 'queued',
        error: '',
    });

    const syncQueue = async () => {
        if (!navigator.onLine) {
            showStatus(t.no_internet, true);
            return;
        }
        const records = (await getAllRecords())
            .filter(record => !offlineSessionId || String(record.session_id) === offlineSessionId)
            .slice(0, 100);
        if (records.length === 0) {
            showStatus(t.nothing_to_sync);
            return;
        }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        try {
            const response = await fetch(syncUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    records: records.map(({ client_uuid, session_id, sale_type, input, recorded_at }) => ({
                        client_uuid, session_id, sale_type, input, recorded_at,
                    })),
                }),
            });
            if (!response.ok) {
                throw new Error(response.status === 419 || response.status === 401
                    ? t.session_stale
                    : fmt(t.server_status, { status: response.status }));
            }
            const result = await response.json();
            const outcomes = new Map(result.results.map(item => [item.client_uuid, item]));
            let reviewCount = 0;
            let errors = 0;
            for (const record of records) {
                const outcome = outcomes.get(record.client_uuid);
                if (outcome?.status === 'synced' || outcome?.status === 'pending_review' || outcome?.status === 'reviewed') {
                    await removeRecord(record.client_uuid);
                    if (outcome.status === 'pending_review') reviewCount++;
                } else {
                    await saveRecord({
                        ...record,
                        state: outcome?.status || 'error',
                        error: outcome?.message || t.no_response,
                    });
                    errors++;
                }
            }
            showStatus(fmt(t.synced, {
                count: records.length - errors,
                review: reviewCount ? fmt(t.synced_review, { count: reviewCount }) : '',
                errors: errors ? fmt(t.synced_errors, { count: errors }) : '',
            }), errors > 0);
            await refreshQueue();
        } catch (error) {
            showStatus(fmt(t.sync_failed_kept, { message: error.message || t.sync_failed }), true);
        }
    };

    document.querySelectorAll('[data-offline-queue-form]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const input = form.elements.input.value;
            const saleType = form.elements.sale_type.value;
            try {
                await saveRecord(createRecord(offlineSessionId, saleType, input));
                form.reset();
                showStatus(t.saved_locally);
                await refreshQueue();
            } catch (error) {
                showStatus(error.message || t.save_failed, true);
            }
        });
    });

    document.querySelectorAll('[data-sync-queue]').forEach(button => {
        button.addEventListener('click', () => syncQueue().catch(error => showStatus(error.message, true)));
    });

    document.querySelectorAll('[data-offline-sale-form]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            const data = new FormData(form);
            const clientUuid = createUuid();
            data.set('client_uuid', clientUuid);
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'text/html' },
                });
                if (!response.ok || response.url.includes('/login')) {
                    showStatusNode(t.server_rejected, true);
                    return;
                }
                window.location.assign(response.url);
            } catch (error) {
                const input = data.get('input');
                try {
                    await saveRecord(createRecord(form.action.match(/sessions\/(\d+)/)?.[1], data.get('sale_type'), input));
                    showStatusNode(t.connection_saved, false);
                    form.reset();
                    await refreshQueue();
                } catch (storageError) {
                    showStatusNode(storageError.message || t.connection_storage_failed, true);
                }
            } finally {
                submitButton.disabled = false;
            }
        });
    });

    const showStatusNode = (message, isError = false) => showStatus(message, isError);

    refreshQueue().catch(error => showStatus(error.message, true));
    window.addEventListener('online', () => {
        showStatus(t.internet_detected);
    });
})();
