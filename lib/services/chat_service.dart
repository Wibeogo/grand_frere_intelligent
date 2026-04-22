import 'dart:io';
import '../config/api_config.dart';
import '../models/message_model.dart';
import 'api_service.dart';

/// Service de conversation avec le Grand Frère (texte, image, audio)
class ChatService {
  /// Envoie un message texte
  static Future<String?> sendMessage(String message, {String? concours}) async {
    final body = <String, dynamic>{'message': message};
    if (concours != null) body['concours'] = concours;

    final response = await ApiService.post(ApiConfig.chatSend, body);
    if (response.success) {
      return response.data?['response'] as String?;
    }

    if (response.isSubscriptionError) {
      throw SubscriptionRequiredException();
    }
    if (response.isRateLimitError) {
      throw RateLimitException(response.errorMessage ?? 'Limite atteinte.');
    }
    throw ApiException(response.errorMessage ?? 'Erreur lors de l\'envoi.');
  }

  /// Envoie une image pour correction par LLaVA
  static Future<String?> sendImage(File imageFile, {String? customPrompt}) async {
    final response = await ApiService.uploadFile(
      ApiConfig.chatUploadImage,
      'image',
      imageFile,
      extraFields: customPrompt != null ? {'prompt': customPrompt} : null,
    );

    if (response.success) {
      return response.data?['response'] as String?;
    }
    if (response.isSubscriptionError) throw SubscriptionRequiredException();
    throw ApiException(response.errorMessage ?? 'Erreur lors de l\'envoi de l\'image.');
  }

  /// Envoie un message vocal (texte transcrit par speech_to_text)
  static Future<String?> sendVoiceText(String transcribedText, {String? concours}) async {
    final body = <String, dynamic>{'transcribed_text': transcribedText};
    if (concours != null) body['concours'] = concours;

    final response = await ApiService.post(ApiConfig.chatUploadAudio, body);
    if (response.success) {
      return response.data?['response'] as String?;
    }
    if (response.isSubscriptionError) throw SubscriptionRequiredException();
    throw ApiException(response.errorMessage ?? 'Erreur du message vocal.');
  }
}

class SubscriptionRequiredException implements Exception {
  final String message = 'Abonnement requis. Souscris pour 2800 FCFA/mois.';
}

class RateLimitException implements Exception {
  final String message;
  const RateLimitException(this.message);
}

class ApiException implements Exception {
  final String message;
  const ApiException(this.message);
}
