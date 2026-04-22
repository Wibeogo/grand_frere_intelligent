import '../config/api_config.dart';
import 'api_service.dart';

/// Service de paiement Senfenico
class PaymentService {
  /// Initie un paiement et retourne l'URL d'autorisation à ouvrir dans une WebView.
  static Future<PaymentInitResult> initiatePayment({String planType = 'monthly'}) async {
    final response = await ApiService.post(
      ApiConfig.paymentInitiate,
      {'plan_type': planType},
    );

    if (response.success && response.data != null) {
      final url = response.data!['authorization_url'] as String?;
      if (url != null && url.isNotEmpty) {
        return PaymentInitResult.success(
          authorizationUrl: url,
          paymentId:        response.data!['payment_id'] as int? ?? 0,
          amount:           response.data!['amount']     as int? ?? ApiConfig.subscriptionPrice,
        );
      }
    }

    return PaymentInitResult.failure(
      response.errorMessage ?? 'Impossible d\'initier le paiement.',
    );
  }
}

class PaymentInitResult {
  final bool success;
  final String? authorizationUrl;
  final int? paymentId;
  final int? amount;
  final String? errorMessage;

  const PaymentInitResult._({
    required this.success,
    this.authorizationUrl,
    this.paymentId,
    this.amount,
    this.errorMessage,
  });

  factory PaymentInitResult.success({
    required String authorizationUrl,
    required int paymentId,
    required int amount,
  }) =>
      PaymentInitResult._(
        success:          true,
        authorizationUrl: authorizationUrl,
        paymentId:        paymentId,
        amount:           amount,
      );

  factory PaymentInitResult.failure(String message) =>
      PaymentInitResult._(success: false, errorMessage: message);
}
