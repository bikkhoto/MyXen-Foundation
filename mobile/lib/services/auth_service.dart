import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_client.dart';

/// Auth state model
class AuthState {
  final bool isAuthenticated;
  final String? token;
  final Map<String, dynamic>? user;
  final bool isLoading;
  final String? error;

  const AuthState({
    this.isAuthenticated = false,
    this.token,
    this.user,
    this.isLoading = false,
    this.error,
  });

  AuthState copyWith({
    bool? isAuthenticated,
    String? token,
    Map<String, dynamic>? user,
    bool? isLoading,
    String? error,
  }) {
    return AuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      token: token ?? this.token,
      user: user ?? this.user,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }
}

/// Auth state provider
final authStateProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier(ref);
});

/// API client provider
final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient();
});

/// Auth notifier - manages authentication state
class AuthNotifier extends StateNotifier<AuthState> {
  final Ref _ref;
  final FlutterSecureStorage _secureStorage = const FlutterSecureStorage();

  AuthNotifier(this._ref) : super(const AuthState()) {
    _checkAuthStatus();
  }

  ApiClient get _apiClient => _ref.read(apiClientProvider);

  /// Check if user is authenticated on app start
  Future<void> _checkAuthStatus() async {
    state = state.copyWith(isLoading: true);
    try {
      final hasToken = await _apiClient.hasToken();
      if (hasToken) {
        // Validate token by fetching profile
        await fetchProfile();
      } else {
        state = state.copyWith(isLoading: false);
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  /// Register a new user
  Future<bool> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiClient.post('/auth/register', data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
      });

      if (response.statusCode == 201) {
        final data = response.data;
        await _apiClient.setToken(data['token']);
        state = state.copyWith(
          isAuthenticated: true,
          token: data['token'],
          user: data['user'],
          isLoading: false,
        );
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  /// Login user
  Future<bool> login({
    required String email,
    required String password,
  }) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiClient.post('/auth/login', data: {
        'email': email,
        'password': password,
      });

      if (response.statusCode == 200) {
        final data = response.data;
        await _apiClient.setToken(data['token']);
        state = state.copyWith(
          isAuthenticated: true,
          token: data['token'],
          user: data['user'],
          isLoading: false,
        );
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  /// Logout user
  Future<void> logout() async {
    try {
      await _apiClient.post('/auth/logout');
    } catch (e) {
      // Ignore errors, still logout locally
    }
    await _apiClient.clearToken();
    state = const AuthState();
  }

  /// Fetch user profile
  Future<void> fetchProfile() async {
    try {
      final response = await _apiClient.get('/auth/profile');
      if (response.statusCode == 200) {
        state = state.copyWith(
          isAuthenticated: true,
          user: response.data['user'],
          isLoading: false,
        );
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  /// Refresh token
  Future<void> refreshToken() async {
    try {
      final response = await _apiClient.post('/auth/refresh');
      if (response.statusCode == 200) {
        await _apiClient.setToken(response.data['token']);
        state = state.copyWith(token: response.data['token']);
      }
    } catch (e) {
      await logout();
    }
  }
}
