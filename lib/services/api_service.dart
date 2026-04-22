import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_config.dart';

/// Service HTTP centralisé avec gestion du token JWT
class ApiService {
  static String? _token;

  // ─── Token Management ─────────────────────────────────────────

  static Future<void> saveToken(String token) async {
    _token = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(ApiConfig.tokenKey, token);
  }

  static Future<String?> getToken() async {
    if (_token != null) return _token;
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(ApiConfig.tokenKey);
    return _token;
  }

  static Future<void> clearToken() async {
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(ApiConfig.tokenKey);
    await prefs.remove(ApiConfig.userDataKey);
  }

  static Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  // ─── En-têtes HTTP ────────────────────────────────────────────

  static Future<Map<String, String>> _getHeaders({bool withAuth = true}) async {
    final headers = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (withAuth) {
      final token = await getToken();
      if (token != null) {
        headers['Authorization'] = 'Bearer $token';
      }
    }
    return headers;
  }

  // ─── Méthodes HTTP ────────────────────────────────────────────

  /// Requête GET
  static Future<ApiResponse> get(String url) async {
    try {
      debugPrint('[ApiService] GET $url');
      final headers = await _getHeaders();
      final response = await http
          .get(Uri.parse(url), headers: headers)
          .timeout(Duration(milliseconds: ApiConfig.receiveTimeout));
      debugPrint('[ApiService] GET $url → ${response.statusCode}');
      return _parseResponse(response);
    } on SocketException catch (e) {
      debugPrint('[ApiService] SocketException GET $url : $e');
      return ApiResponse.error('Pas de connexion internet. Vérifiez votre réseau.');
    } on TimeoutException {
      debugPrint('[ApiService] Timeout GET $url');
      return ApiResponse.error('Le serveur met trop de temps à répondre. Réessayez.');
    } on HandshakeException catch (e) {
      debugPrint('[ApiService] SSL HandshakeException GET $url : $e');
      return ApiResponse.error('Erreur de sécurité SSL. Vérifiez votre connexion.');
    } on http.ClientException catch (e) {
      debugPrint('[ApiService] ClientException GET $url : $e');
      return ApiResponse.error('Impossible de joindre le serveur. Vérifiez votre connexion.');
    } catch (e) {
      debugPrint('[ApiService] Erreur inattendue GET $url : $e');
      return ApiResponse.error('Erreur réseau : ${e.toString()}');
    }
  }

  /// Requête POST avec corps JSON
  static Future<ApiResponse> post(String url, Map<String, dynamic> body, {bool requiresAuth = true}) async {
    try {
      debugPrint('[ApiService] POST $url body=${jsonEncode(body)}');
      final headers = await _getHeaders(withAuth: requiresAuth);
      final response = await http
          .post(Uri.parse(url), headers: headers, body: jsonEncode(body))
          .timeout(Duration(milliseconds: ApiConfig.connectTimeout));
      debugPrint('[ApiService] POST $url → ${response.statusCode} body=${response.body}');
      return _parseResponse(response);
    } on SocketException catch (e) {
      debugPrint('[ApiService] SocketException POST $url : $e');
      return ApiResponse.error('Pas de connexion internet. Vérifiez votre réseau.');
    } on TimeoutException {
      debugPrint('[ApiService] Timeout POST $url');
      return ApiResponse.error('Délai dépassé. Le serveur ne répond pas. Réessayez.');
    } on HandshakeException catch (e) {
      debugPrint('[ApiService] SSL HandshakeException POST $url : $e');
      return ApiResponse.error('Erreur de sécurité SSL. Vérifiez votre connexion.');
    } on http.ClientException catch (e) {
      debugPrint('[ApiService] ClientException POST $url : $e');
      return ApiResponse.error('Impossible de joindre le serveur. Vérifiez votre connexion.');
    } catch (e) {
      debugPrint('[ApiService] Erreur inattendue POST $url : $e');
      return ApiResponse.error('Erreur réseau : ${e.toString()}');
    }
  }

  /// Requête PUT avec corps JSON
  static Future<ApiResponse> put(String url, Map<String, dynamic> body) async {
    try {
      final headers = await _getHeaders();
      final request = http.Request('PUT', Uri.parse(url))
        ..headers.addAll(headers)
        ..body = jsonEncode(body);
      final streamedResponse = await request.send()
          .timeout(Duration(milliseconds: ApiConfig.receiveTimeout));
      final response = await http.Response.fromStream(streamedResponse);
      return _parseResponse(response);
    } on SocketException {
      return ApiResponse.error('Pas de connexion internet.');
    } catch (e) {
      return ApiResponse.error('Erreur réseau : $e');
    }
  }

  /// Upload multipart (image ou audio)
  static Future<ApiResponse> uploadFile(
    String url,
    String fieldName,
    File file, {
    Map<String, String>? extraFields,
  }) async {
    try {
      final token = await getToken();
      final request = http.MultipartRequest('POST', Uri.parse(url));

      if (token != null) {
        request.headers['Authorization'] = 'Bearer $token';
      }

      request.files.add(await http.MultipartFile.fromPath(fieldName, file.path));
      if (extraFields != null) {
        request.fields.addAll(extraFields);
      }

      final streamedResponse = await request.send()
          .timeout(Duration(milliseconds: ApiConfig.sendTimeout));
      final response = await http.Response.fromStream(streamedResponse);
      return _parseResponse(response);
    } on SocketException {
      return ApiResponse.error('Pas de connexion internet.');
    } catch (e) {
      return ApiResponse.error('Erreur lors de l\'upload : $e');
    }
  }

  // ─── Parsing de la réponse ────────────────────────────────────

  static ApiResponse _parseResponse(http.Response response) {
    Map<String, dynamic>? data;
    try {
      data = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (_) {
      data = null;
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return ApiResponse.success(data ?? {});
    }

    // Gestion des erreurs HTTP
    String errorMessage = data?['error'] as String? ??
        data?['message'] as String? ??
        'Erreur ${response.statusCode}';

    // Erreur 403 avec code SUBSCRIPTION_REQUIRED → signaler spécifiquement
    final errorCode = data?['code'] as String?;

    return ApiResponse.httpError(
      statusCode:   response.statusCode,
      message:      errorMessage,
      data:         data,
      errorCode:    errorCode,
    );
  }
}

/// Réponse standardisée de l'API
class ApiResponse {
  final bool success;
  final Map<String, dynamic>? data;
  final String? errorMessage;
  final int? statusCode;
  final String? errorCode;

  const ApiResponse._({
    required this.success,
    this.data,
    this.errorMessage,
    this.statusCode,
    this.errorCode,
  });

  factory ApiResponse.success(Map<String, dynamic> data) =>
      ApiResponse._(success: true, data: data, statusCode: 200);

  factory ApiResponse.error(String message) =>
      ApiResponse._(success: false, errorMessage: message);

  factory ApiResponse.httpError({
    required int statusCode,
    required String message,
    Map<String, dynamic>? data,
    String? errorCode,
  }) =>
      ApiResponse._(
        success:      false,
        statusCode:   statusCode,
        errorMessage: message,
        data:         data,
        errorCode:    errorCode,
      );

  bool get isSubscriptionError => errorCode == 'SUBSCRIPTION_REQUIRED';
  bool get isUnauthorized      => statusCode == 401;
  bool get isRateLimitError    => statusCode == 429;
  bool get isServerError       => (statusCode ?? 0) >= 500;
}
