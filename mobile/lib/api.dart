import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:http/http.dart' as http;

/// Server answered with a status we must act on.
class ApiException implements Exception {
  ApiException(this.statusCode, this.message);

  final int statusCode;
  final String message;

  bool get unauthorized => statusCode == 401;
  bool get forbidden => statusCode == 403;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({
    required this.baseUrl,
    this.token,
    http.Client? client,
    Duration timeout = const Duration(seconds: 25),
  })  : _client = client ?? http.Client(),
        _timeout = timeout;

  static const String apiPrefix = '/api/v1';

  String baseUrl;
  String? token;

  final http.Client _client;
  final Duration _timeout;

  Future<Map<String, dynamic>> login({
    required String loginCode,
    required String password,
    required String deviceName,
  }) =>
      _request('POST', 'auth/login', body: {
        'login_code': loginCode,
        'password': password,
        'device_name': deviceName,
      }, authenticate: false);

  Future<Map<String, dynamic>> me() => _request('GET', 'me');

  Future<void> logout() async {
    await _request('POST', 'auth/logout', body: const {});
  }

  Future<Map<String, dynamic>> openSession(int roundId) =>
      _request('POST', 'sessions/open', body: {'round_id': roundId});

  Future<Map<String, dynamic>> sessionState(int sessionId) =>
      _request('GET', 'sessions/$sessionId/state');

  /// [records] carries client_uuid/session_id/sale_type/input/recorded_at.
  Future<List<Map<String, dynamic>>> uploadSales(
    List<Map<String, dynamic>> records,
  ) async {
    final payload = await _request('POST', 'sales/upload', body: {
      'records': records,
    });

    return _mapList(payload['results']);
  }

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    Map<String, dynamic>? body,
    bool authenticate = true,
  }) async {
    final uri = Uri.parse('$baseUrl$apiPrefix/$path');
    final headers = <String, String>{
      'Accept': 'application/json',
      if (authenticate && token != null) 'Authorization': 'Bearer $token',
    };

    http.Response response;
    if (method == 'GET') {
      response = await _client.get(uri, headers: headers).timeout(_timeout);
    } else {
      headers['Content-Type'] = 'application/json';
      response = await _client
          .post(uri, headers: headers, body: jsonEncode(body ?? const {}))
          .timeout(_timeout);
    }

    final decoded =
        response.body.isEmpty ? <String, dynamic>{} : jsonDecode(response.body);
    final map = decoded is Map ? Map<String, dynamic>.from(decoded) : <String, dynamic>{};

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        response.statusCode,
        (map['message'] ?? 'Server returned ${response.statusCode}.').toString(),
      );
    }

    return map;
  }

  void close() => _client.close();
}

List<Map<String, dynamic>> _mapList(Object? value) {
  if (value is! List) return const [];

  return value
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList();
}

class RoundSummary {
  RoundSummary.fromMap(Map<String, dynamic> map)
      : id = (map['id'] as num?)?.toInt() ?? 0,
        roundNo = (map['round_no'] as num?)?.toInt() ?? 0,
        roundDate = (map['round_date'] ?? '').toString(),
        closeTime = (map['close_time'] ?? '').toString(),
        status = (map['status'] ?? '').toString(),
        hasSession = map['has_session'] == true;

  final int id;
  final int roundNo;
  final String roundDate;
  final String closeTime;
  final String status;
  final bool hasSession;

  String get label => 'Round $roundNo · $roundDate';
}

class SessionSummary {
  SessionSummary.fromMap(Map<String, dynamic> map)
      : id = (map['id'] as num?)?.toInt() ?? 0,
        code = (map['session_code'] ?? '').toString(),
        status = (map['status'] ?? '').toString(),
        roundId = (map['round_id'] as num?)?.toInt() ?? 0;

  final int id;
  final String code;
  final String status;
  final int roundId;
}

class SaleEntry {
  SaleEntry.fromMap(Map<String, dynamic> map)
      : clientUuid = (map['client_uuid'] ?? '').toString(),
        saleType = (map['sale_type'] ?? '2d').toString(),
        input = (map['input'] ?? '').toString(),
        status = (map['status'] ?? '').toString(),
        totalAmount = (map['total_amount'] as num?)?.toDouble() ?? 0,
        createdAt = DateTime.tryParse((map['created_at'] ?? '').toString());

  final String clientUuid;
  final String saleType;
  final String input;
  final String status;
  final double totalAmount;
  final DateTime? createdAt;
}

/// Generates the idempotency key the server deduplicates uploads on.
String newClientUuid() {
  final random = Random.secure();
  final bytes = List<int>.generate(16, (_) => random.nextInt(256));

  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;

  final hex = bytes.map((byte) => byte.toRadixString(16).padLeft(2, '0')).join();

  return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}'
      '-${hex.substring(16, 20)}-${hex.substring(20)}';
}
