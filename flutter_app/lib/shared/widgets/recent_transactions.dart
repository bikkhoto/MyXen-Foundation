import 'package:flutter/material.dart';

class RecentTransactions extends StatelessWidget {
  const RecentTransactions({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    // Placeholder transactions
    final transactions = [
      _Transaction(
        type: 'payment',
        title: 'Coffee Shop',
        subtitle: 'Payment',
        amount: -5.50,
        currency: 'SOL',
        date: DateTime.now().subtract(const Duration(hours: 2)),
      ),
      _Transaction(
        type: 'deposit',
        title: 'Deposit',
        subtitle: 'From Solana Wallet',
        amount: 100.0,
        currency: 'SOL',
        date: DateTime.now().subtract(const Duration(days: 1)),
      ),
      _Transaction(
        type: 'transfer',
        title: 'Transfer to John',
        subtitle: 'Sent',
        amount: -25.0,
        currency: 'MYXN',
        date: DateTime.now().subtract(const Duration(days: 2)),
      ),
    ];

    if (transactions.isEmpty) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              Icon(
                Icons.receipt_long_outlined,
                size: 48,
                color: theme.colorScheme.onSurfaceVariant,
              ),
              const SizedBox(height: 12),
              Text(
                'No transactions yet',
                style: theme.textTheme.bodyLarge?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Card(
      child: ListView.separated(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: transactions.length,
        separatorBuilder: (context, index) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final tx = transactions[index];
          return _TransactionTile(transaction: tx);
        },
      ),
    );
  }
}

class _Transaction {
  final String type;
  final String title;
  final String subtitle;
  final double amount;
  final String currency;
  final DateTime date;

  _Transaction({
    required this.type,
    required this.title,
    required this.subtitle,
    required this.amount,
    required this.currency,
    required this.date,
  });
}

class _TransactionTile extends StatelessWidget {
  final _Transaction transaction;

  const _TransactionTile({required this.transaction});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isPositive = transaction.amount >= 0;

    IconData icon;
    Color iconColor;

    switch (transaction.type) {
      case 'payment':
        icon = Icons.shopping_bag_outlined;
        iconColor = Colors.orange;
        break;
      case 'deposit':
        icon = Icons.add_circle_outline;
        iconColor = Colors.green;
        break;
      case 'withdrawal':
        icon = Icons.remove_circle_outline;
        iconColor = Colors.red;
        break;
      case 'transfer':
        icon = Icons.swap_horiz;
        iconColor = Colors.blue;
        break;
      default:
        icon = Icons.receipt_outlined;
        iconColor = Colors.grey;
    }

    return ListTile(
      leading: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: iconColor.withOpacity(0.1),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(icon, color: iconColor, size: 20),
      ),
      title: Text(
        transaction.title,
        style: theme.textTheme.bodyLarge?.copyWith(
          fontWeight: FontWeight.w500,
        ),
      ),
      subtitle: Text(
        transaction.subtitle,
        style: theme.textTheme.bodySmall?.copyWith(
          color: theme.colorScheme.onSurfaceVariant,
        ),
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            '${isPositive ? '+' : ''}${transaction.amount.toStringAsFixed(2)} ${transaction.currency}',
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.bold,
              color: isPositive ? Colors.green : Colors.red,
            ),
          ),
          Text(
            _formatDate(transaction.date),
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }

  String _formatDate(DateTime date) {
    final now = DateTime.now();
    final difference = now.difference(date);

    if (difference.inHours < 24) {
      return '${difference.inHours}h ago';
    } else if (difference.inDays < 7) {
      return '${difference.inDays}d ago';
    } else {
      return '${date.day}/${date.month}/${date.year}';
    }
  }
}
