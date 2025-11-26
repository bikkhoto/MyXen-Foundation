import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:myxenpay_app/shared/providers/auth_provider.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authStateProvider);
    final user = authState.user;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            onPressed: () {
              // Navigate to settings
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        child: Column(
          children: [
            // Profile Header
            Container(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 50,
                    backgroundColor: theme.colorScheme.primaryContainer,
                    child: Text(
                      user?.name.substring(0, 1).toUpperCase() ?? 'U',
                      style: theme.textTheme.headlineLarge?.copyWith(
                        color: theme.colorScheme.onPrimaryContainer,
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    user?.name ?? 'User',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    user?.email ?? '',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 16),
                  _KycBadge(level: user?.kycLevel ?? 0),
                ],
              ),
            ),
            const Divider(),

            // Menu Items
            _ProfileMenuItem(
              icon: Icons.person_outline,
              title: 'Personal Information',
              subtitle: 'Update your profile details',
              onTap: () {},
            ),
            _ProfileMenuItem(
              icon: Icons.verified_user_outlined,
              title: 'KYC Verification',
              subtitle: 'Verify your identity',
              onTap: () {},
            ),
            _ProfileMenuItem(
              icon: Icons.school_outlined,
              title: 'University ID',
              subtitle: 'Link your student account',
              onTap: () {},
            ),
            _ProfileMenuItem(
              icon: Icons.security_outlined,
              title: 'Security',
              subtitle: 'Password and 2FA',
              onTap: () {},
            ),
            _ProfileMenuItem(
              icon: Icons.store_outlined,
              title: 'Merchant Account',
              subtitle: user?.role == 'merchant' ? 'Manage your business' : 'Register as merchant',
              onTap: () {},
            ),
            _ProfileMenuItem(
              icon: Icons.lock_outlined,
              title: 'Vault',
              subtitle: 'Secure your assets',
              onTap: () {},
            ),
            _ProfileMenuItem(
              icon: Icons.help_outline,
              title: 'Help & Support',
              subtitle: 'Get help with the app',
              onTap: () {},
            ),
            const Divider(),
            _ProfileMenuItem(
              icon: Icons.logout,
              title: 'Logout',
              subtitle: 'Sign out of your account',
              isDestructive: true,
              onTap: () async {
                final confirmed = await showDialog<bool>(
                  context: context,
                  builder: (context) => AlertDialog(
                    title: const Text('Logout'),
                    content: const Text('Are you sure you want to logout?'),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.pop(context, false),
                        child: const Text('Cancel'),
                      ),
                      FilledButton(
                        onPressed: () => Navigator.pop(context, true),
                        child: const Text('Logout'),
                      ),
                    ],
                  ),
                );

                if (confirmed == true) {
                  await ref.read(authStateProvider.notifier).logout();
                  if (context.mounted) {
                    context.go('/login');
                  }
                }
              },
            ),
            const SizedBox(height: 24),
            Text(
              'MyXenPay v1.0.0',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}

class _KycBadge extends StatelessWidget {
  final int level;

  const _KycBadge({required this.level});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = [
      Colors.grey,
      Colors.blue,
      Colors.green,
      Colors.amber,
    ];
    final labels = ['Unverified', 'Basic', 'Verified', 'Premium'];

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: colors[level].withOpacity(0.1),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: colors[level]),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            level > 0 ? Icons.verified : Icons.info_outline,
            size: 16,
            color: colors[level],
          ),
          const SizedBox(width: 6),
          Text(
            'KYC Level $level: ${labels[level]}',
            style: theme.textTheme.bodySmall?.copyWith(
              color: colors[level],
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileMenuItem extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final bool isDestructive;

  const _ProfileMenuItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.isDestructive = false,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = isDestructive ? theme.colorScheme.error : null;

    return ListTile(
      leading: Icon(icon, color: color),
      title: Text(
        title,
        style: TextStyle(color: color),
      ),
      subtitle: Text(
        subtitle,
        style: theme.textTheme.bodySmall?.copyWith(
          color: isDestructive
              ? theme.colorScheme.error.withOpacity(0.7)
              : theme.colorScheme.onSurfaceVariant,
        ),
      ),
      trailing: isDestructive ? null : const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }
}
