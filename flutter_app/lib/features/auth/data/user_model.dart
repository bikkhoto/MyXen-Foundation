class User {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String role;
  final String status;
  final int kycLevel;
  final DateTime? createdAt;
  final Wallet? wallet;

  User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.role,
    required this.status,
    required this.kycLevel,
    this.createdAt,
    this.wallet,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'],
      name: json['name'],
      email: json['email'],
      phone: json['phone'],
      role: json['role'] ?? 'user',
      status: json['status'] ?? 'active',
      kycLevel: json['kyc_level'] ?? 0,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : null,
      wallet: json['wallet'] != null ? Wallet.fromJson(json['wallet']) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'phone': phone,
      'role': role,
      'status': status,
      'kyc_level': kycLevel,
    };
  }
}

class Wallet {
  final int id;
  final int userId;
  final String? solanaAddress;
  final double balance;
  final double myxnBalance;
  final String status;

  Wallet({
    required this.id,
    required this.userId,
    this.solanaAddress,
    required this.balance,
    required this.myxnBalance,
    required this.status,
  });

  factory Wallet.fromJson(Map<String, dynamic> json) {
    return Wallet(
      id: json['id'],
      userId: json['user_id'],
      solanaAddress: json['solana_address'],
      balance: double.tryParse(json['balance'].toString()) ?? 0.0,
      myxnBalance: double.tryParse(json['myxn_balance'].toString()) ?? 0.0,
      status: json['status'] ?? 'active',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'solana_address': solanaAddress,
      'balance': balance,
      'myxn_balance': myxnBalance,
      'status': status,
    };
  }
}
