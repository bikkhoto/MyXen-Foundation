import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../services/auth_service.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Settings'),
      ),
      body: ListView(
        children: [
          // Account Section
          _buildSectionHeader(context, 'Account'),
          _buildSettingsTile(
            context,
            icon: Icons.person,
            title: 'Profile',
            subtitle: 'Manage your account information',
            onTap: () => context.go('/profile'),
          ),
          _buildSettingsTile(
            context,
            icon: Icons.security,
            title: 'Security',
            subtitle: 'Password, 2FA, biometrics',
            onTap: () {
              // TODO: Navigate to security settings
            },
          ),
          _buildSettingsTile(
            context,
            icon: Icons.key,
            title: 'Backup & Recovery',
            subtitle: 'Manage wallet backup',
            onTap: () {
              // TODO: Navigate to backup settings
            },
          ),

          // Preferences Section
          _buildSectionHeader(context, 'Preferences'),
          _buildSwitchTile(
            context,
            icon: Icons.dark_mode,
            title: 'Dark Mode',
            subtitle: 'Use dark theme',
            value: Theme.of(context).brightness == Brightness.dark,
            onChanged: (value) {
              // TODO: Implement theme switching
            },
          ),
          _buildSwitchTile(
            context,
            icon: Icons.notifications,
            title: 'Push Notifications',
            subtitle: 'Receive transaction alerts',
            value: true,
            onChanged: (value) {
              // TODO: Implement notification toggle
            },
          ),
          _buildSettingsTile(
            context,
            icon: Icons.language,
            title: 'Language',
            subtitle: 'English',
            onTap: () {
              // TODO: Show language picker
            },
          ),
          _buildSettingsTile(
            context,
            icon: Icons.attach_money,
            title: 'Currency',
            subtitle: 'USD',
            onTap: () {
              // TODO: Show currency picker
            },
          ),

          // Network Section
          _buildSectionHeader(context, 'Network'),
          _buildSettingsTile(
            context,
            icon: Icons.cloud,
            title: 'Network',
            subtitle: 'Solana Devnet',
            onTap: () {
              // TODO: Show network selector
            },
          ),

          // Support Section
          _buildSectionHeader(context, 'Support'),
          _buildSettingsTile(
            context,
            icon: Icons.help,
            title: 'Help Center',
            subtitle: 'FAQs and guides',
            onTap: () {
              // TODO: Navigate to help
            },
          ),
          _buildSettingsTile(
            context,
            icon: Icons.feedback,
            title: 'Send Feedback',
            subtitle: 'Help us improve',
            onTap: () {
              // TODO: Show feedback form
            },
          ),
          _buildSettingsTile(
            context,
            icon: Icons.info,
            title: 'About',
            subtitle: 'Version 1.0.0',
            onTap: () {
              showAboutDialog(
                context: context,
                applicationName: 'MyXenPay',
                applicationVersion: '1.0.0',
                applicationLegalese: '© 2024 MyXenPay. All rights reserved.',
              );
            },
          ),

          const SizedBox(height: 24),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: OutlinedButton(
              onPressed: () async {
                await ref.read(authStateProvider.notifier).logout();
                if (context.mounted) {
                  context.go('/login');
                }
              },
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.red,
                side: const BorderSide(color: Colors.red),
              ),
              child: const Text('Sign Out'),
            ),
          ),
          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(BuildContext context, String title) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 24, 16, 8),
      child: Text(
        title.toUpperCase(),
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Colors.grey,
              fontWeight: FontWeight.bold,
            ),
      ),
    );
  }

  Widget _buildSettingsTile(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return ListTile(
      leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
      title: Text(title),
      subtitle: Text(subtitle),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }

  Widget _buildSwitchTile(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    required bool value,
    required ValueChanged<bool> onChanged,
  }) {
    return SwitchListTile(
      secondary: Icon(icon, color: Theme.of(context).colorScheme.primary),
      title: Text(title),
      subtitle: Text(subtitle),
      value: value,
      onChanged: onChanged,
    );
  }
}
