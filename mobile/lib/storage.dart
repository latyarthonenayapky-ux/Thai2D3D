import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Keeps the bearer token and the server address on the device.
class DeviceVault {
  DeviceVault({FlutterSecureStorage? storage})
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            );

  static const String _tokenKey = 'agent_token';
  static const String _serverKey = 'server_base_url';
  static const String _agentKey = 'agent_label';

  final FlutterSecureStorage _storage;

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  Future<String?> readServerUrl() => _storage.read(key: _serverKey);

  Future<void> saveServerUrl(String url) =>
      _storage.write(key: _serverKey, value: url);

  Future<String?> readAgentLabel() => _storage.read(key: _agentKey);

  Future<void> saveAgentLabel(String label) =>
      _storage.write(key: _agentKey, value: label);

  Future<void> signOut() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _agentKey);
  }
}
