/// Configuration centrale de l'API
/// Modifiez BASE_URL pour pointer vers votre serveur Hostinger
class ApiConfig {
  // ============================================================
  // 🔧 CONFIGURATION – Modifier ces valeurs avant déploiement
  // ============================================================

  /// URL de base de votre API PHP sur Hostinger
  /// Exemple : 'https://votredomaine.com/api'
  static const String baseUrl = 'https://tiragepromobf.com/api';

  // ============================================================
  // Endpoints
  // ============================================================
  static const String register       = '$baseUrl/register.php';
  static const String login          = '$baseUrl/login.php';
  static const String user           = '$baseUrl/user.php';
  static const String chatSend       = '$baseUrl/chat_send.php';
  static const String chatUploadImage= '$baseUrl/chat_upload_image.php';
  static const String chatUploadAudio= '$baseUrl/chat_upload_audio.php';
  static const String quizGenerate   = '$baseUrl/quiz_generate.php';
  static const String quizSubmit     = '$baseUrl/quiz_submit.php';
  static const String examGenerate   = '$baseUrl/exam_white_generate.php';
  static const String examSubmit     = '$baseUrl/exam_white_submit.php';
  static const String paymentInitiate= '$baseUrl/payment_initiate.php';

  // ============================================================
  // Paramètres
  // ============================================================
  static const int connectTimeout    = 15000; // 15 secondes
  static const int receiveTimeout    = 200000;// 3min 20s (IA peut être lente)
  static const int sendTimeout       = 60000; // 1 minute pour upload fichiers

  /// Prix de l'abonnement en FCFA
  static const int subscriptionPrice = 2800;
  static const String currency       = 'FCFA';

  /// Nom de la clé token dans SharedPreferences
  static const String tokenKey       = 'auth_token';
  static const String userDataKey    = 'user_data';
}
