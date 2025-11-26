import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:myxenpay_app/core/services/api_service.dart';
import 'package:myxenpay_app/core/services/storage_service.dart';
import 'package:myxenpay_app/features/auth/data/user_model.dart';

final authStateProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  final storageService = ref.watch(storageServiceProvider);
  return AuthNotifier(apiService, storageService);
});

class AuthState {
  final bool isAuthenticated;
  final bool isLoading;
  final User? user;
  final String? error;

  AuthState({
    this.isAuthenticated = false,
    this.isLoading = false,
    this.user,
    this.error,
  });

  AuthState copyWith({
    bool? isAuthenticated,
    bool? isLoading,
    User? user,
    String? error,
  }) {
    return AuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      isLoading: isLoading ?? this.isLoading,
      user: user ?? this.user,
      error: error,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  final ApiService _apiService;
  final StorageService _storageService;

  AuthNotifier(this._apiService, this._storageService) : super(AuthState()) {
    _checkAuth();
  }

  Future<void> _checkAuth() async {
    final isLoggedIn = await _storageService.isLoggedIn();
    if (isLoggedIn) {
      await fetchUser();
    }
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiService.post('/auth/login', data: {
        'email': email,
        'password': password,
      });

      if (response.data['success'] == true) {
        final token = response.data['data']['token'];
        await _storageService.saveToken(token);

        final userData = response.data['data']['user'];
        final user = User.fromJson(userData);

        state = state.copyWith(
          isAuthenticated: true,
          isLoading: false,
          user: user,
        );
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.data['message'] ?? 'Login failed',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Login failed. Please try again.',
      );
      return false;
    }
  }

  Future<bool> register(String name, String email, String password) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiService.post('/auth/register', data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
      });

      if (response.data['success'] == true) {
        final token = response.data['data']['token'];
        await _storageService.saveToken(token);

        final userData = response.data['data']['user'];
        final user = User.fromJson(userData);

        state = state.copyWith(
          isAuthenticated: true,
          isLoading: false,
          user: user,
        );
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.data['message'] ?? 'Registration failed',
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Registration failed. Please try again.',
      );
      return false;
    }
  }

  Future<void> fetchUser() async {
    try {
      final response = await _apiService.get('/auth/user');
      if (response.data['success'] == true) {
        final userData = response.data['data'];
        final user = User.fromJson(userData);
        state = state.copyWith(isAuthenticated: true, user: user);
      }
    } catch (e) {
      await logout();
    }
  }

  Future<void> logout() async {
    try {
      await _apiService.post('/auth/logout');
    } catch (_) {}
    await _storageService.clearAll();
    state = AuthState();
  }
}
