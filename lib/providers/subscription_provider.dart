import 'package:flutter/material.dart';
import '../services/payment_service.dart';
import '../services/auth_service.dart';
import 'auth_provider.dart';

/// Provider gérant le statut de l'abonnement et les paiements
class SubscriptionProvider extends ChangeNotifier {
  bool _isLoading      = false;
  String? _error;
  String? _paymentUrl;
  bool _paymentSuccess = false;

  bool    get isLoading      => _isLoading;
  String? get error          => _error;
  String? get paymentUrl     => _paymentUrl;
  bool    get paymentSuccess => _paymentSuccess;

  /// Initie un paiement et récupère l'URL WebView
  Future<String?> initiatePayment() async {
    _isLoading = true;
    _error     = null;
    _paymentUrl= null;
    notifyListeners();

    final result = await PaymentService.initiatePayment();

    _isLoading = false;
    if (result.success && result.authorizationUrl != null) {
      _paymentUrl = result.authorizationUrl;
      notifyListeners();
      return result.authorizationUrl;
    } else {
      _error = result.errorMessage ?? 'Erreur lors de l\'initiation du paiement.';
      notifyListeners();
      return null;
    }
  }

  /// Appelé après retour réussi de la WebView Senfenico
  Future<void> onPaymentSuccess(AuthProvider authProvider) async {
    _paymentSuccess = true;
    notifyListeners();

    // Rafraîchir le profil pour obtenir le nouveau statut premium
    await authProvider.refreshProfile();
    authProvider.markAsPremium();
  }

  void resetPaymentState() {
    _paymentUrl    = null;
    _paymentSuccess= false;
    _error         = null;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
