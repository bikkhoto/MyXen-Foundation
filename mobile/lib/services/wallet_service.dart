import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'api_client.dart';
import 'auth_service.dart';

/// Wallet model
class Wallet {
  final int id;
  final String address;
  final String currency;
  final double balance;
  final double pendingBalance;
  final bool isPrimary;

  Wallet({
    required this.id,
    required this.address,
    required this.currency,
    required this.balance,
    required this.pendingBalance,
    required this.isPrimary,
  });

  factory Wallet.fromJson(Map<String, dynamic> json) {
    return Wallet(
      id: json['id'],
      address: json['address'],
      currency: json['currency'] ?? 'MYXN',
      balance: (json['balance'] as num?)?.toDouble() ?? 0.0,
      pendingBalance: (json['pending_balance'] as num?)?.toDouble() ?? 0.0,
      isPrimary: json['is_primary'] ?? false,
    );
  }
}

/// Transaction model
class Transaction {
  final String uuid;
  final String type;
  final String direction;
  final double amount;
  final double fee;
  final String currency;
  final String status;
  final String? blockchainTx;
  final DateTime createdAt;

  Transaction({
    required this.uuid,
    required this.type,
    required this.direction,
    required this.amount,
    required this.fee,
    required this.currency,
    required this.status,
    this.blockchainTx,
    required this.createdAt,
  });

  factory Transaction.fromJson(Map<String, dynamic> json) {
    return Transaction(
      uuid: json['uuid'],
      type: json['type'],
      direction: json['direction'],
      amount: (json['amount'] as num).toDouble(),
      fee: (json['fee'] as num?)?.toDouble() ?? 0.0,
      currency: json['currency'] ?? 'MYXN',
      status: json['status'],
      blockchainTx: json['blockchain_tx'],
      createdAt: DateTime.parse(json['created_at']),
    );
  }
}

/// Wallet state
class WalletState {
  final List<Wallet> wallets;
  final List<Transaction> transactions;
  final bool isLoading;
  final String? error;

  const WalletState({
    this.wallets = const [],
    this.transactions = const [],
    this.isLoading = false,
    this.error,
  });

  WalletState copyWith({
    List<Wallet>? wallets,
    List<Transaction>? transactions,
    bool? isLoading,
    String? error,
  }) {
    return WalletState(
      wallets: wallets ?? this.wallets,
      transactions: transactions ?? this.transactions,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }

  Wallet? get primaryWallet {
    final primaryWallets = wallets.where((w) => w.isPrimary);
    if (primaryWallets.isNotEmpty) {
      return primaryWallets.first;
    }
    return wallets.isNotEmpty ? wallets.first : null;
  }

  double get totalBalance {
    return wallets.fold(0.0, (sum, w) => sum + w.balance);
  }
}

/// Wallet state provider
final walletStateProvider = StateNotifierProvider<WalletNotifier, WalletState>((ref) {
  return WalletNotifier(ref);
});

/// Wallet notifier - manages wallet state
class WalletNotifier extends StateNotifier<WalletState> {
  final Ref _ref;

  WalletNotifier(this._ref) : super(const WalletState());

  ApiClient get _apiClient => _ref.read(apiClientProvider);

  /// Fetch user wallets
  Future<void> fetchWallets() async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiClient.get('/wallet');
      if (response.statusCode == 200) {
        final walletsJson = response.data['wallets'] as List;
        final wallets = walletsJson.map((w) => Wallet.fromJson(w)).toList();
        state = state.copyWith(wallets: wallets, isLoading: false);
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  /// Fetch transactions for a wallet
  Future<void> fetchTransactions(int walletId) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiClient.get('/wallet/$walletId/transactions');
      if (response.statusCode == 200) {
        final txJson = response.data['data'] as List;
        final transactions = txJson.map((t) => Transaction.fromJson(t)).toList();
        state = state.copyWith(transactions: transactions, isLoading: false);
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  /// Transfer funds
  Future<bool> transfer({
    required String toAddress,
    required double amount,
    String? description,
  }) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiClient.post('/wallet/transfer', data: {
        'to_address': toAddress,
        'amount': amount,
        if (description != null) 'description': description,
      });
      
      if (response.statusCode == 200) {
        await fetchWallets(); // Refresh wallets
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  /// Request withdrawal
  Future<bool> withdraw({
    required String toAddress,
    required double amount,
  }) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiClient.post('/wallet/withdraw', data: {
        'to_address': toAddress,
        'amount': amount,
      });
      
      if (response.statusCode == 202) {
        await fetchWallets();
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }
}
