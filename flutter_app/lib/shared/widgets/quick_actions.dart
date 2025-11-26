import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class QuickActions extends StatelessWidget {
  const QuickActions({super.key});

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 4,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      children: [
        _QuickActionItem(
          icon: Icons.qr_code_scanner,
          label: 'Scan',
          color: Colors.purple,
          onTap: () => context.go('/scan'),
        ),
        _QuickActionItem(
          icon: Icons.send,
          label: 'Send',
          color: Colors.blue,
          onTap: () {},
        ),
        _QuickActionItem(
          icon: Icons.call_received,
          label: 'Receive',
          color: Colors.green,
          onTap: () {},
        ),
        _QuickActionItem(
          icon: Icons.history,
          label: 'History',
          color: Colors.orange,
          onTap: () {},
        ),
        _QuickActionItem(
          icon: Icons.store,
          label: 'Merchants',
          color: Colors.indigo,
          onTap: () {},
        ),
        _QuickActionItem(
          icon: Icons.lock,
          label: 'Vault',
          color: Colors.teal,
          onTap: () {},
        ),
        _QuickActionItem(
          icon: Icons.school,
          label: 'Student',
          color: Colors.pink,
          onTap: () {},
        ),
        _QuickActionItem(
          icon: Icons.more_horiz,
          label: 'More',
          color: Colors.grey,
          onTap: () {},
        ),
      ],
    );
  }
}

class _QuickActionItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _QuickActionItem({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              fontWeight: FontWeight.w500,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}
