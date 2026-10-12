import 'package:flutter/material.dart';

import 'api.dart';
import 'home_screen.dart';
import 'storage.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    super.key,
    required this.vault,
    required this.defaultBaseUrl,
  });

  final DeviceVault vault;
  final String defaultBaseUrl;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late final TextEditingController _server =
      TextEditingController(text: widget.defaultBaseUrl);
  final TextEditingController _loginCode = TextEditingController();
  final TextEditingController _password = TextEditingController();

  bool busy = false;
  String? error;

  @override
  void dispose() {
    _server.dispose();
    _loginCode.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _signIn() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      busy = true;
      error = null;
    });

    final baseUrl = _normaliseBaseUrl(_server.text);
    final api = ApiClient(baseUrl: baseUrl);

    try {
      final payload = await api.login(
        loginCode: _loginCode.text.trim(),
        password: _password.text,
        deviceName: 'WYA Android Agent',
      );

      final token = (payload['token'] ?? '').toString();
      if (token.isEmpty) throw ApiException(500, 'The server returned no token.');

      await widget.vault.saveServerUrl(baseUrl);
      await widget.vault.saveToken(token);

      final agent = payload['agent'];
      if (agent is Map) {
        await widget.vault.saveAgentLabel(
          '${agent['agent_code'] ?? ''} · ${agent['agent_name'] ?? ''}'.trim(),
        );
      }

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => HomeScreen(vault: widget.vault, baseUrl: baseUrl, token: token),
        ),
      );
    } on ApiException catch (failure) {
      setState(() => error = failure.message);
    } catch (failure) {
      setState(() => error = 'Cannot reach the office server. Check the address and connection.');
    } finally {
      api.close();
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('WYA Agent sign in')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text('Office server'),
                TextFormField(
                  controller: _server,
                  keyboardType: TextInputType.url,
                  decoration: const InputDecoration(hintText: 'https://your-vps-address'),
                  validator: (value) =>
                      (value == null || value.trim().isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 14),
                const Text('Mobile login code'),
                TextFormField(
                  controller: _loginCode,
                  autocorrect: false,
                  textCapitalization: TextCapitalization.characters,
                  decoration: const InputDecoration(hintText: 'Given by the office'),
                  validator: (value) =>
                      (value == null || value.trim().length < 4) ? 'At least 4 characters' : null,
                ),
                const SizedBox(height: 14),
                const Text('Password'),
                TextFormField(
                  controller: _password,
                  obscureText: true,
                  validator: (value) =>
                      (value == null || value.length < 6) ? 'At least 6 characters' : null,
                ),
                const SizedBox(height: 20),
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: Text(
                      error!,
                      style: TextStyle(color: Theme.of(context).colorScheme.error),
                    ),
                  ),
                FilledButton(
                  onPressed: busy ? null : _signIn,
                  child: busy
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Sign in'),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Sales typed in this app are uploaded to the office automatically as '
                  'you save them. Without internet they stay on this phone and upload '
                  'when the connection returns.',
                  style: TextStyle(fontSize: 12),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

String _normaliseBaseUrl(String raw) {
  var value = raw.trim();
  if (!value.startsWith('http://') && !value.startsWith('https://')) {
    value = 'https://$value';
  }
  while (value.endsWith('/')) {
    value = value.substring(0, value.length - 1);
  }

  return value;
}
