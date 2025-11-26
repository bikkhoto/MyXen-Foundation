import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:mocktail/mocktail.dart';
import 'package:myxenpay/services/api_client.dart';

class MockFlutterSecureStorage extends Mock implements FlutterSecureStorage {}

void main() {
  group('ApiClient', () {
    late MockFlutterSecureStorage mockStorage;
    late ApiClient apiClient;

    setUp(() {
      mockStorage = MockFlutterSecureStorage();
      apiClient = ApiClient(secureStorage: mockStorage);
    });

    test('stores token in secure storage', () async {
      const testToken = 'test-token-123';
      
      when(() => mockStorage.write(
        key: any(named: 'key'),
        value: any(named: 'value'),
      )).thenAnswer((_) async {});

      await apiClient.setToken(testToken);

      verify(() => mockStorage.write(
        key: ApiClient.tokenKey,
        value: testToken,
      )).called(1);
    });

    test('clears token from secure storage', () async {
      when(() => mockStorage.delete(
        key: any(named: 'key'),
      )).thenAnswer((_) async {});

      await apiClient.clearToken();

      verify(() => mockStorage.delete(key: ApiClient.tokenKey)).called(1);
    });

    test('checks if token exists', () async {
      when(() => mockStorage.read(
        key: any(named: 'key'),
      )).thenAnswer((_) async => 'existing-token');

      final hasToken = await apiClient.hasToken();

      expect(hasToken, isTrue);
    });

    test('returns false when no token exists', () async {
      when(() => mockStorage.read(
        key: any(named: 'key'),
      )).thenAnswer((_) async => null);

      final hasToken = await apiClient.hasToken();

      expect(hasToken, isFalse);
    });
  });
}
