import 'dart:io';
import 'package:flutter/material.dart';
import '../models/message_model.dart';

/// Bulle de message dans le chat
class MessageBubble extends StatelessWidget {
  final MessageModel message;
  const MessageBubble({super.key, required this.message});

  static const _primary   = Color(0xFF5C35D9);
  static const _userBubble= Color(0xFF3A2080);
  static const _botBg     = Color(0xFF1A1730);

  @override
  Widget build(BuildContext context) {
    if (message.isTyping) return _buildTypingIndicator();

    final isUser = message.isUser;
    final isError= message.status == MessageStatus.error;

    return Padding(
      padding: EdgeInsets.only(
        left:   isUser ? 60 : 12,
        right:  isUser ? 12 : 60,
        top:    4,
        bottom: 4,
      ),
      child: Row(
        mainAxisAlignment: isUser ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isUser) _Avatar(),

          const SizedBox(width: 8),

          Flexible(
            child: Column(
              crossAxisAlignment: isUser ? CrossAxisAlignment.end : CrossAxisAlignment.start,
              children: [
                // Image si présente
                if (message.hasImage) _buildImageContent(message.imageUrl!),

                // Texte
                Container(
                  padding:     const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration:  BoxDecoration(
                    color:        isUser ? _userBubble : _botBg,
                    borderRadius: BorderRadius.only(
                      topLeft:     const Radius.circular(18),
                      topRight:    const Radius.circular(18),
                      bottomLeft:  Radius.circular(isUser ? 18 : 4),
                      bottomRight: Radius.circular(isUser ? 4 : 18),
                    ),
                    border: isError
                        ? Border.all(color: Colors.redAccent.withOpacity(0.5))
                        : isUser
                            ? null
                            : Border.all(color: const Color(0xFF3A3060)),
                    boxShadow: isUser ? [
                      BoxShadow(
                        color:     _primary.withOpacity(0.15),
                        blurRadius:8,
                        offset:    const Offset(0, 2),
                      ),
                    ] : null,
                  ),
                  child: SelectableText(
                    message.content,
                    style: TextStyle(
                      color:     isError ? Colors.redAccent : Colors.white,
                      fontSize:  15,
                      height:    1.5,
                    ),
                  ),
                ),

                // Timestamp
                Padding(
                  padding: const EdgeInsets.only(top: 2, left: 4, right: 4),
                  child: Text(
                    _formatTime(message.createdAt),
                    style: const TextStyle(color: Color(0xFF6B6490), fontSize: 10),
                  ),
                ),
              ],
            ),
          ),

          if (isUser) const SizedBox(width: 8),
        ],
      ),
    );
  }

  Widget _buildImageContent(String imagePath) {
    final isNetwork = imagePath.startsWith('http');
    return Container(
      margin:       const EdgeInsets.only(bottom: 4),
      width:        220,
      height:       160,
      clipBehavior: Clip.antiAlias,
      decoration:   BoxDecoration(borderRadius: BorderRadius.circular(14)),
      child: isNetwork
          ? Image.network(imagePath, fit: BoxFit.cover)
          : Image.file(File(imagePath), fit: BoxFit.cover),
    );
  }

  Widget _buildTypingIndicator() {
    return Padding(
      padding: const EdgeInsets.only(left: 12, top: 4, bottom: 4, right: 60),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.start,
        children: [
          _Avatar(),
          const SizedBox(width: 8),
          Container(
            padding:     const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration:  BoxDecoration(
              color:        _botBg,
              borderRadius: const BorderRadius.only(
                topLeft:     Radius.circular(18),
                topRight:    Radius.circular(18),
                bottomRight: Radius.circular(18),
                bottomLeft:  Radius.circular(4),
              ),
              border: Border.all(color: const Color(0xFF3A3060)),
            ),
            child: const _TypingDots(),
          ),
        ],
      ),
    );
  }

  String _formatTime(DateTime dt) {
    final h = dt.hour.toString().padLeft(2, '0');
    final m = dt.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }
}

class _Avatar extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    width:  32, height: 32,
    decoration: const BoxDecoration(
      gradient: LinearGradient(colors: [Color(0xFF5C35D9), Color(0xFF9B59B6)]),
      shape:    BoxShape.circle,
    ),
    child: const Center(child: Text('🎓', style: TextStyle(fontSize: 16))),
  );
}

class _TypingDots extends StatefulWidget {
  const _TypingDots();
  @override
  State<_TypingDots> createState() => _TypingDotsState();
}

class _TypingDotsState extends State<_TypingDots> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double>   _anim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(duration: const Duration(milliseconds: 900), vsync: this)
      ..repeat();
    _anim = Tween<double>(begin: 0, end: 3).animate(_ctrl);
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _anim,
      builder:   (_, __) => Row(
        mainAxisSize: MainAxisSize.min,
        children:     List.generate(3, (i) {
          final opacity = _anim.value > i ? 1.0 : 0.3;
          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 2),
            child: Opacity(
              opacity: opacity,
              child: Container(
                width: 8, height: 8,
                decoration: const BoxDecoration(
                  color: Color(0xFF9D96C0), shape: BoxShape.circle,
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}
