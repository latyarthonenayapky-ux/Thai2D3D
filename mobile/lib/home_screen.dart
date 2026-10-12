import 'package:flutter/material.dart';

import 'api.dart';
import 'login_screen.dart';
import 'queue.dart';
import 'storage.dart';
import 'sync.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.vault,
    required this.baseUrl,
    required this.token,
  });

  final DeviceVault vault;
  final String baseUrl;
  final String token;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with WidgetsBindingObserver {
  late final ApiClient _api =
      ApiClient(baseUrl: widget.baseUrl, token: widget.token);
  late final QueueStore _queue = QueueStore();
  late final SyncEngine _sync = SyncEngine(api: _api, queue: _queue);

  final TextEditingController _input = TextEditingController();

  String _agentLabel = 'Agent';
  String _saleType = '2d';
  int? _sessionId;
  String _sessionLabel = 'No session open yet';
  List<RoundSummary> _rounds = const [];
  List<SaleEntry> _recent = const [];
  double _soldTotal = 0;
  bool _loading = true;
  String? _notice;
  bool _wasUploading = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _sync.start();
    _sync.addListener(_onSyncChanged);
    _bootstrap();
  }

  @override
  void dispose() {
    _sync.removeListener(_onSyncChanged);
    WidgetsBinding.instance.removeObserver(this);
    _sync.stop();
    _api.close();
    _input.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _sync.drain();
    }
  }

  void _onSyncChanged() {
    if (_wasUploading && !_sync.uploading) {
      _refreshLedger();
    }
    _wasUploading = _sync.uploading;
    if (mounted) setState(() {});
  }

  Future<void> _bootstrap() async {
    setState(() => _loading = true);

    try {
      final me = await _api.me();
      final agent = me['agent'];
      if (agent is Map) {
        _agentLabel =
            '${agent['agent_code'] ?? ''} · ${agent['agent_name'] ?? ''}'.trim();
      }

      _rounds = _roundsFrom(me['rounds']);

      final sessions = (me['sessions'] is List)
          ? (me['sessions'] as List)
              .whereType<Map>()
              .map((row) => SessionSummary.fromMap(Map<String, dynamic>.from(row)))
              .toList()
          : <SessionSummary>[];

      if (sessions.isNotEmpty) {
        _sessionId = sessions.first.id;
        _sessionLabel = sessions.first.code;
      }

      await _sync.refresh();
      await _refreshLedger();
    } on ApiException catch (failure) {
      if (failure.unauthorized || failure.forbidden) {
        await _sync.stop();
        _sync.blockedReason = failure.message;
      } else {
        _notice = failure.message;
      }
    } catch (_) {
      _notice = 'Cannot reach the office server.';
    }

    if (mounted) setState(() => _loading = false);
  }

  Future<void> _refreshLedger() async {
    final session = _sessionId;
    if (session == null) return;

    try {
      final payload = await _api.sessionState(session);
      final entries = (payload['entries'] is List)
          ? (payload['entries'] as List)
              .whereType<Map>()
              .map((row) => SaleEntry.fromMap(Map<String, dynamic>.from(row)))
              .toList()
          : <SaleEntry>[];
      final totals = payload['totals'] is Map
          ? Map<String, dynamic>.from(payload['totals'] as Map)
          : <String, dynamic>{};

      if (!mounted) return;
      setState(() {
        _recent = entries;
        _soldTotal = (totals['sold_amount_2d'] as num?)?.toDouble() ?? 0;
      });
    } on ApiException {
      // The ledger view is a convenience; the queue keeps uploading regardless.
    }
  }

  Future<void> _openSession(RoundSummary round) async {
    setState(() => _loading = true);

    try {
      final payload = await _api.openSession(round.id);
      final session = payload['session'];
      if (session is Map) {
        final summary = SessionSummary.fromMap(Map<String, dynamic>.from(session));
        _sessionId = summary.id;
        _sessionLabel = summary.code;
      }
      await _refreshLedger();
      _notice = 'Session ${_sessionLabel} is open for sales.';
    } on ApiException catch (failure) {
      _notice = failure.message;
    }

    if (mounted) setState(() => _loading = false);
  }

  Future<void> _record() async {
    final session = _sessionId;
    final value = _input.text.trim();

    if (session == null) {
      setState(() => _notice = 'Open a Round session before recording sales.');
      return;
    }
    if (value.isEmpty) {
      setState(() => _notice = 'Type the numbers and amount, for example 121000.');
      return;
    }

    _input.clear();
    await _sync.submit(sessionId: session, saleType: _saleType, input: value);

    if (mounted) {
      FocusScope.of(context).unfocus();
      setState(() => _notice = 'Saved. Uploading now.');
    }
  }

  Future<void> _signOut() async {
    try {
      await _api.logout();
    } on ApiException {
      // A dead token still clears the device.
    }

    await _sync.stop();
    await widget.vault.signOut();

    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (_) => LoginScreen(vault: widget.vault, defaultBaseUrl: widget.baseUrl),
      ),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(_agentLabel),
        actions: [
          IconButton(
            onPressed: _bootstrap,
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
          IconButton(
            onPressed: _signOut,
            icon: const Icon(Icons.logout),
            tooltip: 'Sign out',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _bootstrap,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _UploadBanner(sync: _sync),
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Round session', style: theme.textTheme.titleMedium),
                    const SizedBox(height: 4),
                    Text(_sessionLabel),
                    if (_rounds.isNotEmpty && _sessionId == null) ...[
                      const SizedBox(height: 10),
                      ..._rounds.map(
                        (round) => ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(round.label),
                          subtitle: Text('Closes ${round.closeTime}'),
                          trailing: FilledButton.tonal(
                            onPressed: _loading ? null : () => _openSession(round),
                            child: const Text('Open'),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('New sale', style: theme.textTheme.titleMedium),
                    const SizedBox(height: 10),
                    SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(value: '2d', label: Text('2D')),
                        ButtonSegment(value: '3d', label: Text('3D')),
                      ],
                      selected: {_saleType},
                      onSelectionChanged: (values) =>
                          setState(() => _saleType = values.first),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _input,
                      keyboardType: TextInputType.text,
                      textInputAction: TextInputAction.done,
                      onSubmitted: (_) => _record(),
                      decoration: const InputDecoration(
                        labelText: 'Numbers and amount',
                        hintText: '121000  or  A1000',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    FilledButton.icon(
                      onPressed: _record,
                      icon: const Icon(Icons.cloud_upload_outlined),
                      label: const Text('Record and upload'),
                    ),
                  ],
                ),
              ),
            ),
            if (_notice != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(_notice!, style: theme.textTheme.bodyMedium),
              ),
            if (_sync.problems.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text('Needs attention', style: theme.textTheme.titleMedium),
              ..._sync.problems.map(
                (problem) => Card.outlined(
                  child: ListTile(
                    title: Text('${problem.saleType.toUpperCase()} · ${problem.input}'),
                    subtitle: Text(problem.message ?? 'Not uploaded yet.'),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(
                          icon: const Icon(Icons.refresh),
                          tooltip: 'Try again',
                          onPressed: () => _sync.retryProblem(problem.clientUuid),
                        ),
                        IconButton(
                          icon: const Icon(Icons.delete_outline),
                          tooltip: 'Discard',
                          onPressed: () => _sync.discardProblem(problem.clientUuid),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Uploaded in this session', style: theme.textTheme.titleMedium),
                Text(_soldTotal.toStringAsFixed(2),
                    style: theme.textTheme.titleMedium),
              ],
            ),
            const SizedBox(height: 6),
            if (_recent.isEmpty)
              Text('No sales recorded yet.', style: theme.textTheme.bodyMedium)
            else
              ..._recent.map(
                (entry) => ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  title: Text('${entry.saleType.toUpperCase()} · ${entry.input}'),
                  subtitle: Text(entry.status),
                  trailing: Text(entry.totalAmount.toStringAsFixed(0)),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _UploadBanner extends StatelessWidget {
  const _UploadBanner({required this.sync});

  final SyncEngine sync;

  @override
  Widget build(BuildContext context) {
    final Color color;
    final IconData icon;
    final String text;

    if (sync.needsSignIn) {
      color = Colors.red.shade50;
      icon = Icons.lock_outline;
      text = sync.blockedReason ?? 'Please sign in again.';
    } else if (sync.uploading) {
      color = Colors.blue.shade50;
      icon = Icons.cloud_upload_outlined;
      text = 'Uploading ${sync.waiting} sale(s)…';
    } else if (!sync.online) {
      color = Colors.orange.shade50;
      icon = Icons.wifi_off;
      text = 'Offline · ${sync.waiting} sale(s) saved on this phone';
    } else if (sync.waiting > 0) {
      color = Colors.amber.shade50;
      icon = Icons.pending_outlined;
      text = '${sync.waiting} sale(s) waiting to upload';
    } else {
      color = Colors.green.shade50;
      icon = Icons.cloud_done_outlined;
      text = 'All sales uploaded';
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon),
          const SizedBox(width: 10),
          Expanded(child: Text(text)),
        ],
      ),
    );
  }
}

List<RoundSummary> _roundsFrom(Object? value) {
  if (value is! List) return const [];

  return value
      .whereType<Map>()
      .map((row) => RoundSummary.fromMap(Map<String, dynamic>.from(row)))
      .toList();
}
