import 'package:flutter/material.dart';
import '../models/user_model.dart';
import '../services/auth_service.dart';
import '../services/api_service.dart';

/// Provider gérant l'état d'authentification global
class AuthProvider extends ChangeNotifier {
  UserModel? _user;
  bool _isLoading = false;
  bool _isInitialized = false;
  String? _errorMessage;

  UserModel? get user         => _user;
  bool       get isLoading    => _isLoading;
  bool       get isInitialized=> _isInitialized;
  String?    get errorMessage => _errorMessage;
  bool       get isLoggedIn   => _user != null;
  bool       get isPremium    => _user?.isPremium ?? false;
  bool       get isExpired    => _user?.isExpired ?? true;

  /// Initialisation au démarrage : récupère le user en cache + vérifie le profil
  Future<void> initialize() async {
    _isLoading = true;
    notifyListeners();

    try {
      final isLogged = await ApiService.isLoggedIn();
      if (isLogged) {
        // Charger depuis le cache d'abord (instantané)
        _user = await AuthService.getCachedUser();
        notifyListeners();

        // Puis actualiser depuis l'API (en background)
        final freshUser = await AuthService.getProfile();
        if (freshUser != null) {
          _user = freshUser;
        }
      }
    } catch (e) {
      _user = null;
      _errorMessage = 'Erreur de connexion au serveur (${e.toString()})';
    } finally {
      _isLoading      = false;
      _isInitialized  = true;
      notifyListeners();
    }
  }

  /// Inscription
  Future<String?> register({
    required String email,
    required String password,
    required String phone,
    String? fullName,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    final result = await AuthService.register(
      email: email, password: password, phone: phone, fullName: fullName,
    );

    _isLoading = false;
    if (result.success) {
      _user = result.user;
      notifyListeners();
      return null; // Succès
    } else {
      _errorMessage = result.errorMessage;
      notifyListeners();
      return result.errorMessage; // Retourner l'erreur
    }
  }

  /// Connexion
  Future<String?> login({required String email, required String password}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    final result = await AuthService.login(email: email, password: password);

    _isLoading = false;
    if (result.success) {
      _user = result.user;
      notifyListeners();
      return null;
    } else {
      _errorMessage = result.errorMessage;
      notifyListeners();
      return result.errorMessage;
    }
  }

  /// Déconnexion
  Future<void> logout() async {
    await AuthService.logout();
    _user = null;
    notifyListeners();
  }

  /// Mettre à jour le concours ciblé
  Future<void> updateConcours(String concoursKey) async {
    final success = await AuthService.updateProfile(concoursCible: concoursKey);
    if (success && _user != null) {
      _user = _user!.copyWith(concoursCible: concoursKey);
      notifyListeners();
    }
  }

  /// Rafraîchir le profil depuis l'API
  Future<void> refreshProfile() async {
    final user = await AuthService.getProfile();
    if (user != null) {
      _user = user;
      notifyListeners();
    }
  }

  /// Mettre à jour le statut premium localement (après paiement)
  void markAsPremium() {
    if (_user != null) {
      _user = _user!.copyWith(
        isPremium: true,
        subscriptionStatus: 'premium',
      );
      notifyListeners();
    }
  }
}
