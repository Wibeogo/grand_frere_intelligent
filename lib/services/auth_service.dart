import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_config.dart';
import '../models/user_model.dart';
import 'api_service.dart';

/// Service d'authentification (login, register, profil)
class AuthService {
  // ─── Inscription ─────────────────────────────────────────────

  static Future<AuthResult> register({
    required String email,
    required String password,
    required String phone,
    String? fullName,
  }) async {
    final response = await ApiService.post(
      ApiConfig.register,
      {
        'email':     email,
        'password':  password,
        'phone':     phone,
        if (fullName != null) 'full_name': fullName,
      },
      requiresAuth: false,
    );

    if (response.success && response.data != null) {
      final token = response.data!['token'] as String?;
      final userData = response.data!['user'] as Map<String, dynamic>?;

      if (token != null && userData != null) {
        await ApiService.saveToken(token);
        final user = UserModel.fromJson(userData);
        await _saveUserData(user);
        return AuthResult.success(user: user, token: token);
      }
    }

    return AuthResult.failure(response.errorMessage ?? 'Erreur lors de l\'inscription.');
  }

  // ─── Connexion ───────────────────────────────────────────────

  static Future<AuthResult> login({
    required String email,
    required String password,
  }) async {
    final response = await ApiService.post(
      ApiConfig.login,
      {'email': email, 'password': password},
      requiresAuth: false,
    );

    if (response.success && response.data != null) {
      final token    = response.data!['token']   as String?;
      final userData = response.data!['user']    as Map<String, dynamic>?;

      if (token != null && userData != null) {
        await ApiService.saveToken(token);
        final user = UserModel.fromJson(userData);
        await _saveUserData(user);
        return AuthResult.success(user: user, token: token);
      }
    }

    return AuthResult.failure(response.errorMessage ?? 'Email ou mot de passe incorrect.');
  }

  // ─── Profil ───────────────────────────────────────────────────

  static Future<UserModel?> getProfile() async {
    final response = await ApiService.get(ApiConfig.user);
    if (response.success && response.data?['user'] != null) {
      final user = UserModel.fromJson(response.data!['user'] as Map<String, dynamic>);
      await _saveUserData(user);
      return user;
    }
    return null;
  }

  static Future<bool> updateProfile({
    String? fullName,
    String? concoursCible,
    String? fcmToken,
  }) async {
    final body = <String, dynamic>{};
    if (fullName      != null) body['full_name']      = fullName;
    if (concoursCible != null) body['concours_cible'] = concoursCible;
    if (fcmToken      != null) body['fcm_token']      = fcmToken;

    final response = await ApiService.put(ApiConfig.user, body);
    return response.success;
  }

  // ─── Déconnexion ─────────────────────────────────────────────

  static Future<void> logout() async {
    await ApiService.clearToken();
  }

  // ─── Persistance locale ───────────────────────────────────────

  static Future<void> _saveUserData(UserModel user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(ApiConfig.userDataKey, user.toJsonString());
  }

  static Future<UserModel?> getCachedUser() async {
    final prefs = await SharedPreferences.getInstance();
    final jsonStr = prefs.getString(ApiConfig.userDataKey);
    if (jsonStr != null) {
      return UserModel.fromJsonString(jsonStr);
    }
    return null;
  }
}

/// Résultat d'une opération d'authentification
class AuthResult {
  final bool success;
  final UserModel? user;
  final String? token;
  final String? errorMessage;

  const AuthResult._({
    required this.success,
    this.user,
    this.token,
    this.errorMessage,
  });

  factory AuthResult.success({required UserModel user, required String token}) =>
      AuthResult._(success: true, user: user, token: token);

  factory AuthResult.failure(String message) =>
      AuthResult._(success: false, errorMessage: message);
}
