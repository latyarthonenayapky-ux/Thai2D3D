import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';

import 'api.dart';
import 'queue.dart';

/// Keeps every recorded sale moving to the server without the Agent pressing
/// a sync button: entries upload as they are saved, and a failed upload is
/// retried on the timer, on reconnect, and whenever the app is opened again.
class SyncEngine extends ChangeNotifier {
  SyncEngine({
    required this.api,
    required this.queue,
    Connectivity? connectivity,
  }) : _connectivity = connectivity ?? Connectivity();

  static const int batchSize = 100;
  static const Duration tick = Duration(seconds: 20);

  final ApiClient api;
  final QueueStore queue;
  final Connectivity _connectivity;

  StreamSubscription<List<ConnectivityResult>>? _connectionSub;
  Timer? _ticker;

  bool uploading = false;
  bool online = true;
  int waiting = 0;
  String message = '';
  String? blockedReason;
  List<QueuedSale> problems = const [];

  bool get needsSignIn => blockedReason != null;

  void start() {
    _ticker ??= Timer.periodic(tick, (_) => drain());
    _connectionSub ??= _connectivity.onConnectivityChanged.listen((results) {
      final connected = results.any((value) => value != ConnectivityResult.none);
      if (connected != online) {
        online = connected;
        notifyListeners();
      }
      if (connected) drain();
    });

    refresh();
    drain();
  }

  Future<void> stop() async {
    _ticker?.cancel();
    _ticker = null;
    await _connectionSub?.cancel();
    _connectionSub = null;
    await queue.close();
  }

  Future<void> submit({
    required int sessionId,
    required String saleType,
    required String input,
  }) async {
    await queue.enqueue(
      clientUuid: newClientUuid(),
      sessionId: sessionId,
      saleType: saleType,
      input: input,
      recordedAt: DateTime.now().toUtc().toIso8601String(),
    );

    await refresh();
    notifyListeners();
    unawaited(drain());
  }

  Future<void> refresh() async {
    waiting = await queue.waitingCount();
    problems = await queue.needsAttention();
  }

  Future<void> retryProblem(String clientUuid) async {
    await queue.retry(clientUuid);
    await refresh();
    notifyListeners();
    unawaited(drain());
  }

  Future<void> discardProblem(String clientUuid) async {
    await queue.remove(clientUuid);
    await refresh();
    notifyListeners();
  }

  Future<void> drain() async {
    if (uploading || blockedReason != null || !online) return;

    final batch = await queue.waiting(limit: batchSize);
    if (batch.isEmpty) {
      await refresh();
      notifyListeners();
      return;
    }

    uploading = true;
    notifyListeners();

    try {
      final results =
          await api.uploadSales(batch.map((sale) => sale.toUploadRecord()).toList());

      final byUuid = <String, Map<String, dynamic>>{
        for (final result in results)
          (result['client_uuid'] ?? '').toString(): result,
      };

      for (final sale in batch) {
        final outcome = byUuid[sale.clientUuid];
        final status = (outcome?['status'] ?? '').toString();
        final note = (outcome?['message'] ?? '').toString();

        if (status == 'synced' || status == 'pending_review' || status == 'reviewed') {
          await queue.remove(sale.clientUuid);
          message = status == 'synced'
              ? '${sale.input} uploaded.'
              : '${sale.input} is waiting for the office to review.';
          continue;
        }

        if (status == 'invalid') {
          await queue.mark(
            clientUuid: sale.clientUuid,
            state: 'invalid',
            message: note.isEmpty ? 'This entry could not be read.' : note,
          );
          continue;
        }

        final gaveUp = await queue.registerAttempt(
          sale.clientUuid,
          note.isEmpty ? 'The office did not accept this entry.' : note,
        );
        if (gaveUp) {
          message = '${sale.input} needs attention.';
        }
      }
    } on ApiException catch (error) {
      if (error.unauthorized || error.forbidden) {
        blockedReason = error.message;
      } else {
        message = error.message;
      }
    } on TimeoutException {
      message = 'The server took too long to answer. Entries will be retried.';
    } catch (_) {
      message = 'No connection. Entries stay on this phone until it returns.';
    } finally {
      uploading = false;
      await refresh();
      notifyListeners();
    }
  }
}
