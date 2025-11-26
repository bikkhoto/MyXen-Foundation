import 'package:flutter/material.dart';

class AppConstants {
  AppConstants._();

  // App Info
  static const String appName = 'MyXenPay';
  static const String appVersion = '1.0.0';

  // Colors
  static const Color primaryColor = Color(0xFF6366F1);
  static const Color secondaryColor = Color(0xFF8B5CF6);
  static const Color accentColor = Color(0xFF10B981);
  static const Color errorColor = Color(0xFFEF4444);
  static const Color warningColor = Color(0xFFF59E0B);
  static const Color successColor = Color(0xFF10B981);

  // API Configuration
  static const String baseUrl = 'http://localhost:8000/api';
  static const Duration apiTimeout = Duration(seconds: 30);

  // Storage Keys
  static const String tokenKey = 'auth_token';
  static const String userKey = 'user_data';
  static const String themeKey = 'theme_mode';

  // Validation
  static const int minPasswordLength = 8;
  static const int maxPasswordLength = 128;
  static const int pinLength = 6;

  // Currency
  static const String defaultCurrency = 'SOL';
  static const List<String> supportedCurrencies = ['SOL', 'MYXN'];

  // Transaction Limits
  static const double minTransactionAmount = 0.000000001;
  static const double maxTransactionAmount = 1000000;

  // Pagination
  static const int defaultPageSize = 15;
}
