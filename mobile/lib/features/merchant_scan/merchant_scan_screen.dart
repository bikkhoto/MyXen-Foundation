import 'package:flutter/material.dart';

/// MerchantScanScreen - QR code scanner for merchant payments
/// 
/// TODO: Implement QR code scanning with mobile_scanner package
/// TODO: Parse payment request from QR code
/// TODO: Confirm and process payment
class MerchantScanScreen extends StatelessWidget {
  const MerchantScanScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Scan QR Code'),
      ),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Placeholder for camera scanner
            Container(
              width: 280,
              height: 280,
              decoration: BoxDecoration(
                border: Border.all(
                  color: Theme.of(context).colorScheme.primary,
                  width: 3,
                ),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.qr_code_scanner,
                    size: 80,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Camera Scanner',
                    style: TextStyle(fontSize: 16),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'TODO: Implement QR scanning',
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey.shade600,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 32),
            Text(
              'Point your camera at a merchant\nQR code to make a payment',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: 24),
            // Manual entry option
            OutlinedButton.icon(
              onPressed: () {
                // TODO: Show manual payment entry dialog
              },
              icon: const Icon(Icons.edit),
              label: const Text('Enter Code Manually'),
            ),
          ],
        ),
      ),
    );
  }
}
