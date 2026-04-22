import 'dart:io';
import 'package:flutter/material.dart';
import '../models/message_model.dart';
import '../services/chat_service.dart';

/// Provider gérant l'état de la conversation avec le Grand Frère
class ChatProvider extends ChangeNotifier {
  final List<MessageModel> _messages = [];
  bool _isLoading   = false;
  String? _error;
  String? _currentConcours;

  List<MessageModel> get messages  => List.unmodifiable(_messages);
  bool               get isLoading => _isLoading;
  String?            get error     => _error;
  bool               get hasError  => _error != null;

  set currentConcours(String? v) {
    _currentConcours = v;
    notifyListeners();
  }

  // ─── Envoi de message texte ───────────────────────────────────

  Future<void> sendMessage(String text) async {
    if (text.trim().isEmpty || _isLoading) return;

    _error = null;
    _addUserMessage(MessageModel.userMessage(text));
    _setLoading(true);

    try {
      final response = await ChatService.sendMessage(
        text,
        concours: _currentConcours,
      );
      _addBotMessage(response ?? 'Désolé, je n\'ai pas pu répondre. Réessaie.');
    } on SubscriptionRequiredException catch (e) {
      _error = e.message;
      _addSystemMessage('🔒 ' + e.message);
    } on RateLimitException catch (e) {
      _addSystemMessage('⏳ ' + e.message);
    } on ApiException catch (e) {
      _addSystemMessage('❌ ' + e.message);
    } finally {
      _setLoading(false);
    }
  }

  // ─── Envoi d'image ────────────────────────────────────────────

  Future<void> sendImage(File imageFile) async {
    if (_isLoading) return;
    _error = null;

    _messages.add(MessageModel.imageMessage(imageFile.path));
    _setLoading(true);
    notifyListeners();

    try {
      final response = await ChatService.sendImage(imageFile);
      _addBotMessage(response ?? 'Correction non disponible. Réessaie.');
    } on SubscriptionRequiredException catch (e) {
      _error = e.message;
      _addSystemMessage('🔒 ' + e.message);
    } on ApiException catch (e) {
      _addSystemMessage('❌ Erreur : ' + e.message);
    } finally {
      _setLoading(false);
    }
  }

  // ─── Envoi de message vocal (texte transcrit) ─────────────────

  Future<void> sendVoiceMessage(String transcribedText) async {
    if (transcribedText.trim().isEmpty || _isLoading) return;
    _error = null;

    _addUserMessage(MessageModel.userMessage('🎤 $transcribedText'));
    _setLoading(true);

    try {
      final response = await ChatService.sendVoiceText(
        transcribedText,
        concours: _currentConcours,
      );
      _addBotMessage(response ?? 'Désolé, je n\'ai pas pu répondre.');
    } on SubscriptionRequiredException catch (e) {
      _error = e.message;
      _addSystemMessage('🔒 ' + e.message);
    } on ApiException catch (e) {
      _addSystemMessage('❌ ' + e.message);
    } finally {
      _setLoading(false);
    }
  }

  // ─── Helpers privés ───────────────────────────────────────────

  void _addUserMessage(MessageModel msg) {
    _messages.add(msg);
    notifyListeners();
  }

  void _addBotMessage(String content) {
    _messages.add(MessageModel(
      content:   content,
      isUser:    false,
      createdAt: DateTime.now(),
      status:    MessageStatus.sent,
    ));
    notifyListeners();
  }

  void _addSystemMessage(String content) {
    _messages.add(MessageModel(
      content:   content,
      isUser:    false,
      createdAt: DateTime.now(),
      status:    MessageStatus.error,
    ));
    notifyListeners();
  }

  void _setLoading(bool value) {
    _isLoading = value;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void clearMessages() {
    _messages.clear();
    notifyListeners();
  }
}
