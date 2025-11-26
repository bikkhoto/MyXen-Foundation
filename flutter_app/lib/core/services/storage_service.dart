import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:myxenpay_app/core/constants/app_constants.dart';

final storageServiceProvider = Provider<StorageService>((ref) {
  return StorageService();
});

class StorageService {
  final FlutterSecureStorage _secureStorage = const FlutterSecureStorage();

  // Token management
  Future<void> saveToken(String token) async {
    await _secureStorage.write(key: AppConstants.tokenKey, value: token);
  }

  Future<String?> getToken() async {
    return await _secureStorage.read(key: AppConstants.tokenKey);
  }

  Future<void> clearToken() async {
    await _secureStorage.delete(key: AppConstants.tokenKey);
  }

  // User data management
  Future<void> saveUserData(String userData) async {
    await _secureStorage.write(key: AppConstants.userKey, value: userData);
  }

  Future<String?> getUserData() async {
    return await _secureStorage.read(key: AppConstants.userKey);
  }

  Future<void> clearUserData() async {
    await _secureStorage.delete(key: AppConstants.userKey);
  }

  // Clear all data
  Future<void> clearAll() async {
    await _secureStorage.deleteAll();
  }

  // Check if user is logged in
  Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }
}
