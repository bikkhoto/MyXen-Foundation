import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// ServicesScreen - Hub for all MyXen services
class ServicesScreen extends StatelessWidget {
  const ServicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('MyXen Services'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildSectionHeader(context, 'Financial Services'),
            _buildServiceGrid(context, [
              _ServiceItem(
                icon: Icons.payment,
                title: 'MyXenPay',
                subtitle: 'Payments & Transfers',
                route: '/wallet',
                color: Colors.blue,
              ),
              _ServiceItem(
                icon: Icons.send,
                title: 'Remittance',
                subtitle: 'Cross-border Transfers',
                route: '/remittance',
                color: Colors.green,
              ),
              _ServiceItem(
                icon: Icons.lock_clock,
                title: 'MyXenLocker',
                subtitle: 'Token Locking & Vesting',
                route: '/locker',
                color: Colors.purple,
              ),
              _ServiceItem(
                icon: Icons.account_balance,
                title: 'Treasury',
                subtitle: 'Treasury Management',
                route: '/treasury',
                color: Colors.indigo,
              ),
            ]),
            
            _buildSectionHeader(context, 'Commerce'),
            _buildServiceGrid(context, [
              _ServiceItem(
                icon: Icons.store,
                title: 'Store',
                subtitle: 'Marketplace',
                route: '/store',
                color: Colors.orange,
              ),
              _ServiceItem(
                icon: Icons.storefront,
                title: 'Merchant',
                subtitle: 'Business Solutions',
                route: '/merchant',
                color: Colors.teal,
              ),
              _ServiceItem(
                icon: Icons.work,
                title: 'Freelancer',
                subtitle: 'Gig Marketplace',
                route: '/freelancer',
                color: Colors.cyan,
              ),
              _ServiceItem(
                icon: Icons.flight,
                title: 'Travel',
                subtitle: 'Book & Pay',
                route: '/travel',
                color: Colors.lightBlue,
              ),
            ]),
            
            _buildSectionHeader(context, 'Enterprise'),
            _buildServiceGrid(context, [
              _ServiceItem(
                icon: Icons.school,
                title: 'University',
                subtitle: 'Campus Platform',
                route: '/university',
                color: Colors.amber,
              ),
              _ServiceItem(
                icon: Icons.payments,
                title: 'Payroll',
                subtitle: 'Employee Payments',
                route: '/payroll',
                color: Colors.deepOrange,
              ),
              _ServiceItem(
                icon: Icons.security,
                title: 'Multisig',
                subtitle: 'Multi-signature Wallets',
                route: '/multisig',
                color: Colors.blueGrey,
              ),
              _ServiceItem(
                icon: Icons.admin_panel_settings,
                title: 'Admin Panel',
                subtitle: 'Back Office',
                route: '/admin',
                color: Colors.grey,
              ),
            ]),
            
            _buildSectionHeader(context, 'Community'),
            _buildServiceGrid(context, [
              _ServiceItem(
                icon: Icons.people,
                title: 'Social',
                subtitle: 'MyXen.Social',
                route: '/social',
                color: Colors.pink,
              ),
              _ServiceItem(
                icon: Icons.how_to_vote,
                title: 'Governance',
                subtitle: 'DAO Voting',
                route: '/governance',
                color: Colors.deepPurple,
              ),
              _ServiceItem(
                icon: Icons.emoji_emotions,
                title: 'Meme Engine',
                subtitle: 'Create & Earn',
                route: '/memes',
                color: Colors.yellow.shade700,
              ),
              _ServiceItem(
                icon: Icons.volunteer_activism,
                title: 'Charity',
                subtitle: 'MyXen Life',
                route: '/charity',
                color: Colors.red,
              ),
            ]),
            
            _buildSectionHeader(context, 'Impact'),
            _buildServiceGrid(context, [
              _ServiceItem(
                icon: Icons.female,
                title: 'Women Program',
                subtitle: 'Empowerment',
                route: '/women',
                color: Colors.pinkAccent,
              ),
              _ServiceItem(
                icon: Icons.card_giftcard,
                title: 'Rewards',
                subtitle: 'Earn MYXN',
                route: '/rewards',
                color: Colors.lime,
              ),
              _ServiceItem(
                icon: Icons.people_outline,
                title: 'Referral',
                subtitle: 'Invite & Earn',
                route: '/referral',
                color: Colors.lightGreen,
              ),
            ]),
            
            _buildSectionHeader(context, 'Support & Developer'),
            _buildServiceGrid(context, [
              _ServiceItem(
                icon: Icons.help_center,
                title: 'Help Center',
                subtitle: 'Support',
                route: '/helpdesk',
                color: Colors.brown,
              ),
              _ServiceItem(
                icon: Icons.code,
                title: 'Developer',
                subtitle: 'API Portal',
                route: '/developer',
                color: Colors.black87,
              ),
              _ServiceItem(
                icon: Icons.notifications,
                title: 'Messaging',
                subtitle: 'Notifications',
                route: '/messaging',
                color: Colors.blue.shade300,
              ),
              _ServiceItem(
                icon: Icons.analytics,
                title: 'Analytics',
                subtitle: 'Reports',
                route: '/analytics',
                color: Colors.green.shade300,
              ),
            ]),
            
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionHeader(BuildContext context, String title) {
    return Padding(
      padding: const EdgeInsets.only(top: 16, bottom: 12),
      child: Text(
        title,
        style: Theme.of(context).textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.bold,
            ),
      ),
    );
  }

  Widget _buildServiceGrid(BuildContext context, List<_ServiceItem> items) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 4,
        childAspectRatio: 0.85,
        crossAxisSpacing: 8,
        mainAxisSpacing: 8,
      ),
      itemCount: items.length,
      itemBuilder: (context, index) {
        final item = items[index];
        return _buildServiceCard(context, item);
      },
    );
  }

  Widget _buildServiceCard(BuildContext context, _ServiceItem item) {
    return InkWell(
      onTap: () {
        // TODO: Navigate to service when implemented
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${item.title} - Coming Soon'),
            duration: const Duration(seconds: 1),
          ),
        );
      },
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          color: item.color.withOpacity(0.1),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: item.color.withOpacity(0.2),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                item.icon,
                color: item.color,
                size: 24,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              item.title,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
              textAlign: TextAlign.center,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            Text(
              item.subtitle,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Colors.grey,
                    fontSize: 9,
                  ),
              textAlign: TextAlign.center,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}

class _ServiceItem {
  final IconData icon;
  final String title;
  final String subtitle;
  final String route;
  final Color color;

  _ServiceItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.route,
    required this.color,
  });
}
