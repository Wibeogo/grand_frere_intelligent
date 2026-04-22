/// Modèle d'un message de conversation
class MessageModel {
  final int? id;
  final String content;
  final bool isUser;
  final String? imageUrl;
  final String? audioUrl;
  final DateTime createdAt;
  final MessageStatus status;

  const MessageModel({
    this.id,
    required this.content,
    required this.isUser,
    this.imageUrl,
    this.audioUrl,
    required this.createdAt,
    this.status = MessageStatus.sent,
  });

  bool get hasImage => imageUrl?.isNotEmpty == true;
  bool get hasAudio => audioUrl?.isNotEmpty == true;
  bool get isTyping => status == MessageStatus.typing;

  factory MessageModel.fromJson(Map<String, dynamic> json) {
    return MessageModel(
      id:        json['id'] as int?,
      content:   json['message'] as String? ?? '',
      isUser:    (json['is_user'] as int?) == 1,
      imageUrl:  json['image_url'] as String?,
      audioUrl:  json['audio_url'] as String?,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'] as String) ?? DateTime.now()
          : DateTime.now(),
    );
  }

  /// Crée un message "typing indicator" de l'IA
  factory MessageModel.typing() {
    return MessageModel(
      content:   '',
      isUser:    false,
      createdAt: DateTime.now(),
      status:    MessageStatus.typing,
    );
  }

  /// Crée un message utilisateur local (avant envoi)
  factory MessageModel.userMessage(String text) {
    return MessageModel(
      content:   text,
      isUser:    true,
      createdAt: DateTime.now(),
      status:    MessageStatus.pending,
    );
  }

  /// Crée un message image utilisateur
  factory MessageModel.imageMessage(String imagePath) {
    return MessageModel(
      content:   '📷 Photo envoyée pour correction',
      isUser:    true,
      imageUrl:  imagePath,
      createdAt: DateTime.now(),
      status:    MessageStatus.pending,
    );
  }

  MessageModel copyWith({MessageStatus? status, String? content}) {
    return MessageModel(
      id:        id,
      content:   content ?? this.content,
      isUser:    isUser,
      imageUrl:  imageUrl,
      audioUrl:  audioUrl,
      createdAt: createdAt,
      status:    status ?? this.status,
    );
  }
}

enum MessageStatus { pending, sent, error, typing }
