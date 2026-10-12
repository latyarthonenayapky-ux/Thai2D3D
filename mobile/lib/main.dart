import 'package:flutter/material.dart';

import 'home_screen.dart';
import 'login_screen.dart';
import 'storage.dart';

/// Agents can point the app at their office VPS from the sign-in screen;
/// this is only the address baked into the APK build.
const String defaultServerUrl = String.fromEnvironment(
  'WYA_SERVER_URL',
  defaultValue: 'https://wya.example.com',
);

void main() => runApp(const WyaAgentApp());

class WyaAgentApp extends StatefulWidget {
  const WyaAgentApp({super.key});

  @override
  State<WyaAgentApp> createState() => _WyaAgentAppState();
}

class _WyaAgentAppState extends State<WyaAgentApp> {
  final DeviceVault vault = DeviceVault();
  Widget? _startupScreen;

  @override
  void initState() {
    super.initState();
    _restore();
  }

  Future<void> _restore() async {
    final baseUrl = await vault.readServerUrl() ?? defaultServerUrl;
    final token = await vault.readToken();

    if (!mounted) return;

    setState(() {
      _startupScreen = token == null || token.isEmpty
          ? LoginScreen(vault: vault, defaultBaseUrl: baseUrl)
          : HomeScreen(vault: vault, baseUrl: baseUrl, token: token);
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'WYA Agent',
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF0F6B3C)),
      ),
      home: _startupScreen ??
          const Scaffold(body: Center(child: CircularProgressIndicator())),
    );
  }
}
