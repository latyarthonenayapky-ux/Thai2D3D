import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// A sale that is waiting on the phone. It survives app restarts, so an
/// upload that failed halfway is retried instead of lost.
class QueuedSale {
  QueuedSale({
    required this.clientUuid,
    required this.sessionId,
    required this.saleType,
    required this.input,
    required this.recordedAt,
    required this.state,
    required this.attempts,
    this.message,
  });

  factory QueuedSale.fromMap(Map<String, Object?> map) => QueuedSale(
        clientUuid: map['client_uuid'] as String,
        sessionId: (map['session_id'] as int?) ?? 0,
        saleType: (map['sale_type'] as String?) ?? '2d',
        input: (map['input'] as String?) ?? '',
        recordedAt: (map['recorded_at'] as String?) ?? '',
        state: (map['state'] as String?) ?? 'queued',
        attempts: (map['attempts'] as int?) ?? 0,
        message: map['message'] as String?,
      );

  final String clientUuid;
  final int sessionId;
  final String saleType;
  final String input;
  final String recordedAt;
  final String state;
  final int attempts;
  final String? message;

  bool get isWaiting => state == 'queued';

  Map<String, dynamic> toUploadRecord() => {
        'client_uuid': clientUuid,
        'session_id': sessionId,
        'sale_type': saleType,
        'input': input,
        'recorded_at': recordedAt,
      };
}

class QueueStore {
  static const String _fileName = 'wya_agent_queue.db';
  static const int _version = 1;

  Database? _db;

  Future<Database> _open() async {
    final existing = _db;
    if (existing != null) return existing;

    final path = p.join(await getDatabasesPath(), _fileName);

    return _db = await openDatabase(
      path,
      version: _version,
      onCreate: (database, _) async {
        await database.execute('''
          CREATE TABLE sale_queue (
            client_uuid TEXT PRIMARY KEY,
            session_id INTEGER NOT NULL,
            sale_type TEXT NOT NULL,
            input TEXT NOT NULL,
            recorded_at TEXT NOT NULL,
            state TEXT NOT NULL,
            attempts INTEGER NOT NULL,
            message TEXT,
            created_at TEXT NOT NULL
          )
        ''');
        await database.execute(
            'CREATE INDEX sale_queue_state ON sale_queue (state, created_at)');
      },
    );
  }

  Future<void> enqueue({
    required String clientUuid,
    required int sessionId,
    required String saleType,
    required String input,
    required String recordedAt,
  }) async {
    final database = await _open();
    await database.insert('sale_queue', {
      'client_uuid': clientUuid,
      'session_id': sessionId,
      'sale_type': saleType,
      'input': input,
      'recorded_at': recordedAt,
      'state': 'queued',
      'attempts': 0,
      'created_at': DateTime.now().toUtc().toIso8601String(),
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<List<QueuedSale>> waiting({int limit = 100}) async {
    final database = await _open();
    final rows = await database.query(
      'sale_queue',
      where: 'state = ?',
      whereArgs: ['queued'],
      orderBy: 'created_at ASC',
      limit: limit,
    );

    return rows.map(QueuedSale.fromMap).toList();
  }

  Future<int> waitingCount() async {
    final database = await _open();
    final rows = await database.rawQuery(
        'SELECT COUNT(*) AS total FROM sale_queue WHERE state = ?', ['queued']);

    return (rows.first['total'] as int?) ?? 0;
  }

  Future<List<QueuedSale>> all() async {
    final database = await _open();
    final rows =
        await database.query('sale_queue', orderBy: 'created_at DESC');

    return rows.map(QueuedSale.fromMap).toList();
  }

  Future<List<QueuedSale>> needsAttention() async {
    final database = await _open();
    final rows = await database.query(
      'sale_queue',
      where: 'state IN (?, ?)',
      whereArgs: ['invalid', 'stuck'],
      orderBy: 'created_at DESC',
    );

    return rows.map(QueuedSale.fromMap).toList();
  }

  Future<void> retry(String clientUuid) async {
    final database = await _open();
    await database.update(
      'sale_queue',
      {'state': 'queued', 'attempts': 0, 'message': null},
      where: 'client_uuid = ?',
      whereArgs: [clientUuid],
    );
  }

  Future<void> remove(String clientUuid) async {
    final database = await _open();
    await database.delete('sale_queue',
        where: 'client_uuid = ?', whereArgs: [clientUuid]);
  }

  Future<void> mark({
    required String clientUuid,
    required String state,
    String? message,
  }) async {
    final database = await _open();
    await database.update(
      'sale_queue',
      {'state': state, 'message': message},
      where: 'client_uuid = ?',
      whereArgs: [clientUuid],
    );
  }

  /// Returns true when the entry gave up and needs the Agent's attention.
  Future<bool> registerAttempt(
    String clientUuid,
    String message, {
    int maxAttempts = 8,
  }) async {
    final database = await _open();
    final rows = await database.query('sale_queue',
        columns: ['attempts'],
        where: 'client_uuid = ?',
        whereArgs: [clientUuid],
        limit: 1);

    if (rows.isEmpty) return false;

    final attempts = ((rows.first['attempts'] as int?) ?? 0) + 1;
    final giveUp = attempts >= maxAttempts;

    await database.update(
      'sale_queue',
      {
        'attempts': attempts,
        'message': message,
        'state': giveUp ? 'stuck' : 'queued',
      },
      where: 'client_uuid = ?',
      whereArgs: [clientUuid],
    );

    return giveUp;
  }

  Future<void> close() async {
    final database = _db;
    if (database != null) {
      await database.close();
      _db = null;
    }
  }
}
