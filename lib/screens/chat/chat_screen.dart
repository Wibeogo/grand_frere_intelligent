import 'dart:io';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import 'package:speech_to_text/speech_to_text.dart';
import '../../models/message_model.dart';
import '../../providers/auth_provider.dart';
import '../../providers/chat_provider.dart';
import '../../widgets/message_bubble.dart';
import '../../widgets/premium_banner.dart';

/// Écran de chat principal avec le Grand Frère Intelligent
class ChatScreen extends StatefulWidget {
  const ChatScreen({super.key});
  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _textCtrl      = TextEditingController();
  final _scrollCtrl    = ScrollController();
  final _imagePicker   = ImagePicker();
  final _speechToText  = SpeechToText();

  bool _isRecording    = false;
  bool _speechAvail    = false;
  String _spokenText   = '';

  static const _primary = Color(0xFF5C35D9);
  static const _gold    = Color(0xFFF0B429);
  static const _bgDark  = Color(0xFF0D0B1F);
  static const _card    = Color(0xFF231E42);

  @override
  void initState() {
    super.initState();
    _initSpeech();
    // Initialiser avec un message de bienvenue
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final chat = context.read<ChatProvider>();
      if (chat.messages.isEmpty) {
        chat.sendMessage(
          'Bonjour ! Présente-toi brièvement et dis-moi comment tu peux m\'aider à préparer mon concours.',
        );
      }
    });
  }

  Future<void> _initSpeech() async {
    _speechAvail = await _speechToText.initialize();
    setState(() {});
  }

  @override
  void dispose() {
    _textCtrl.dispose();
    _scrollCtrl.dispose();
    _speechToText.stop();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve:    Curves.easeOut,
        );
      }
    });
  }

  // ─── Envoi texte ──────────────────────────────────────────────

  void _sendText() {
    final text = _textCtrl.text.trim();
    if (text.isEmpty) return;
    _textCtrl.clear();

    final auth = context.read<AuthProvider>();
    context.read<ChatProvider>()
      ..currentConcours = auth.user?.concoursCible
      ..sendMessage(text);
    _scrollToBottom();
  }

  // ─── Sélection image ──────────────────────────────────────────

  Future<void> _pickImage() async {
    final source = await _showImageSourceDialog();
    if (source == null) return;

    final picked = await _imagePicker.pickImage(
      source:   source,
      maxWidth: 1024,
      imageQuality: 80,
    );

    if (picked != null && mounted) {
      context.read<ChatProvider>().sendImage(File(picked.path));
      _scrollToBottom();
    }
  }

  Future<ImageSource?> _showImageSourceDialog() {
    return showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: _card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(height: 8),
          Container(width: 40, height: 4, color: Colors.white24,
            decoration: BoxDecoration(borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 16),
          const Text('Envoyer une photo', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16)),
          const SizedBox(height: 8),
          ListTile(
            leading:   const Icon(Icons.camera_alt, color: _primary),
            title:     const Text('Prendre une photo', style: TextStyle(color: Colors.white)),
            onTap:     () => Navigator.pop(ctx, ImageSource.camera),
          ),
          ListTile(
            leading:   const Icon(Icons.photo_library, color: _primary),
            title:     const Text('Choisir depuis la galerie', style: TextStyle(color: Colors.white)),
            onTap:     () => Navigator.pop(ctx, ImageSource.gallery),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  // ─── Micro (speech_to_text) ───────────────────────────────────

  Future<void> _toggleRecording() async {
    if (!_speechAvail) {
      _showSnack('Reconnaissance vocale non disponible sur cet appareil.');
      return;
    }

    if (_isRecording) {
      await _speechToText.stop();
      setState(() => _isRecording = false);

      if (_spokenText.isNotEmpty) {
        final auth = context.read<AuthProvider>();
        context.read<ChatProvider>()
          ..currentConcours = auth.user?.concoursCible
          ..sendVoiceMessage(_spokenText);
        setState(() => _spokenText = '');
      }
    } else {
      setState(() { _isRecording = true; _spokenText = ''; });
      await _speechToText.listen(
        onResult:     (result) => setState(() => _spokenText = result.recognizedWords),
        localeId:     'fr_FR',
        listenFor:    const Duration(seconds: 30),
        pauseFor:     const Duration(seconds: 3),
        cancelOnError:true,
      );
    }
  }

  void _showSnack(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: _card),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final chat = context.watch<ChatProvider>();
    _scrollToBottom();

    return Scaffold(
      backgroundColor: _bgDark,
      appBar: _buildAppBar(auth),
      body: Column(
        children: [
          // Bannière premium si expiré
          if (!(auth.user?.isPremium ?? false))
            const PremiumBanner(),

          // Messages
          Expanded(
            child: chat.messages.isEmpty
                ? _buildEmptyState()
                : ListView.builder(
                    controller: _scrollCtrl,
                    padding:    const EdgeInsets.symmetric(vertical: 8),
                    itemCount:  chat.messages.length + (chat.isLoading ? 1 : 0),
                    itemBuilder: (ctx, i) {
                      if (i == chat.messages.length) {
                        return MessageBubble(
                          message: MessageModel.typing(),
                        );
                      }
                      return MessageBubble(message: chat.messages[i]);
                    },
                  ),
          ),

          // Affichage transcription live
          if (_isRecording && _spokenText.isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              color:   _primary.withOpacity(0.1),
              child: Row(
                children: [
                  const Icon(Icons.mic, color: Colors.redAccent, size: 16),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _spokenText,
                      style: const TextStyle(color: Colors.white70, fontSize: 13, fontStyle: FontStyle.italic),
                    ),
                  ),
                ],
              ),
            ),

          // Zone de saisie
          _buildInputBar(chat),
        ],
      ),
      // Drawer navigation
      drawer: _buildDrawer(auth),
    );
  }

  AppBar _buildAppBar(AuthProvider auth) {
    final concours = auth.user?.concoursCible;
    final emoji    = concours != null
        ? ConcoursEmoji.get(concours)
        : '🎓';

    return AppBar(
      title: Column(
        children: [
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(emoji, style: const TextStyle(fontSize: 18)),
              const SizedBox(width: 8),
              const Text('Grand Frère Intelligent',
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17)),
            ],
          ),
          if (auth.user?.concoursCible != null)
            Text(
              ConcourNom.get(concours!),
              style: const TextStyle(fontSize: 11, color: Color(0xFF9D96C0)),
            ),
        ],
      ),
      actions: [
        // Statut premium
        Padding(
          padding: const EdgeInsets.only(right: 8),
          child: GestureDetector(
            onTap: () => Navigator.pushNamed(context, '/subscription'),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: auth.isPremium
                      ? [_gold, const Color(0xFFE67E22)]
                      : [const Color(0xFF3A3060), const Color(0xFF2A2050)],
                ),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    auth.isPremium ? Icons.star : Icons.lock,
                    size: 12, color: Colors.white,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    auth.isPremium ? 'Premium' : 'Gratuit',
                    style: const TextStyle(
                      color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildInputBar(ChatProvider chat) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFF1A1730),
        boxShadow: [
          BoxShadow(
            color:   Colors.black.withOpacity(0.3),
            blurRadius: 10,
            offset:  const Offset(0, -2),
          ),
        ],
      ),
      child: Row(
        children: [
          // Bouton image
          IconButton(
            onPressed: _pickImage,
            icon: const Icon(Icons.add_photo_alternate, color: Color(0xFF6B6490)),
            tooltip: 'Envoyer une photo',
          ),

          // Champ texte
          Expanded(
            child: Container(
              decoration: BoxDecoration(
                color:        const Color(0xFF231E42),
                borderRadius: BorderRadius.circular(24),
                border:       Border.all(color: const Color(0xFF3A3060)),
              ),
              child: TextField(
                controller:   _textCtrl,
                style:        const TextStyle(color: Colors.white, fontSize: 15),
                maxLines:     4,
                minLines:     1,
                onSubmitted:  (_) => _sendText(),
                decoration:   const InputDecoration(
                  hintText:        'Pose ta question...',
                  hintStyle:       TextStyle(color: Color(0xFF6B6490)),
                  border:          InputBorder.none,
                  contentPadding:  EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                ),
              ),
            ),
          ),

          const SizedBox(width: 8),

          // Bouton micro
          GestureDetector(
            onTap: _toggleRecording,
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              width:  44, height: 44,
              decoration: BoxDecoration(
                color:        _isRecording ? Colors.redAccent : const Color(0xFF231E42),
                shape:        BoxShape.circle,
                border:       Border.all(
                  color: _isRecording ? Colors.redAccent : const Color(0xFF3A3060),
                ),
                boxShadow: _isRecording
                    ? [BoxShadow(color: Colors.redAccent.withOpacity(0.4), blurRadius: 10)]
                    : null,
              ),
              child: Icon(
                _isRecording ? Icons.mic : Icons.mic_none,
                color: _isRecording ? Colors.white : const Color(0xFF6B6490),
                size: 20,
              ),
            ),
          ),

          const SizedBox(width: 8),

          // Bouton envoyer
          GestureDetector(
            onTap: chat.isLoading ? null : _sendText,
            child: Container(
              width:  44, height: 44,
              decoration: BoxDecoration(
                gradient:     const LinearGradient(
                  colors: [_primary, Color(0xFF9B59B6)],
                ),
                shape:        BoxShape.circle,
                boxShadow:    [
                  BoxShadow(
                    color:   _primary.withOpacity(0.4),
                    blurRadius: 10,
                  ),
                ],
              ),
              child: chat.isLoading
                  ? const Padding(
                      padding: EdgeInsets.all(12),
                      child:   CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                    )
                  : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 80, height: 80,
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [_primary, Color(0xFF9B59B6)]),
              borderRadius: BorderRadius.circular(20),
            ),
            child: const Center(child: Text('🎓', style: TextStyle(fontSize: 40))),
          ),
          const SizedBox(height: 20),
          const Text(
            'Bonjour ! Je suis ton Grand Frère 👋',
            style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          const Text(
            'Pose-moi n\'importe quelle question sur ton concours.',
            style: TextStyle(color: Color(0xFF9D96C0)),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 32),
          // Suggestions rapides
          Wrap(
            spacing: 8, runSpacing: 8,
            alignment: WrapAlignment.center,
            children: [
              '📝 Explique-moi la Constitution BF',
              '🧮 Un exercice de maths',
              '📚 Questions de culture générale',
              '⚖️ Droits et obligations',
            ].map((s) => GestureDetector(
              onTap: () {
                _textCtrl.text = s.substring(3);
                _sendText();
              },
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(
                  color:        const Color(0xFF231E42),
                  borderRadius: BorderRadius.circular(20),
                  border:       Border.all(color: const Color(0xFF3A3060)),
                ),
                child: Text(s, style: const TextStyle(color: Color(0xFFBBB5D8), fontSize: 13)),
              ),
            )).toList(),
          ),
        ],
      ),
    );
  }

  Drawer _buildDrawer(AuthProvider auth) {
    return Drawer(
      backgroundColor: const Color(0xFF1A1730),
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          DrawerHeader(
            decoration: const BoxDecoration(
              gradient: LinearGradient(colors: [_primary, Color(0xFF9B59B6)]),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const CircleAvatar(
                  radius: 28,
                  backgroundColor: Colors.white24,
                  child: Text('🎓', style: TextStyle(fontSize: 28)),
                ),
                const SizedBox(height: 8),
                Text(
                  auth.user?.displayName ?? 'Candidat',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16),
                ),
                Text(
                  auth.user?.email ?? '',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
          _drawerItem(Icons.chat_bubble_outline, 'Chat', '/chat'),
          _drawerItem(Icons.quiz_outlined, 'Quiz', '/quiz'),
          _drawerItem(Icons.article_outlined, 'Examen blanc', '/exam'),
          _drawerItem(Icons.bar_chart_rounded, 'Tableau de bord', '/dashboard'),
          _drawerItem(Icons.school_outlined, 'Changer de concours', '/concours'),
          const Divider(color: Color(0xFF3A3060)),
          _drawerItem(Icons.star_outline, 'Mon abonnement', '/subscription'),
          _drawerItem(Icons.settings_outlined, 'Paramètres', '/settings'),
          const Divider(color: Color(0xFF3A3060)),
          ListTile(
            leading:  const Icon(Icons.logout, color: Colors.redAccent),
            title:    const Text('Déconnexion', style: TextStyle(color: Colors.redAccent)),
            onTap:    () async {
              Navigator.pop(context);
              await auth.logout();
              if (mounted) Navigator.pushReplacementNamed(context, '/login');
            },
          ),
        ],
      ),
    );
  }

  ListTile _drawerItem(IconData icon, String title, String route) {
    return ListTile(
      leading:  Icon(icon, color: const Color(0xFF9D96C0)),
      title:    Text(title, style: const TextStyle(color: Colors.white)),
      onTap:    () {
        Navigator.pop(context);
        if (ModalRoute.of(context)?.settings.name != route) {
          Navigator.pushNamed(context, route);
        }
      },
    );
  }
}

/// Helpers pour affichage concours
class ConcoursEmoji {
  static String get(String key) => {
    'police': '👮', 'enaref': '🏦', 'enseignement_primaire': '📚',
    'enseignement_secondaire': '🎓', 'sante': '🏥', 'douanes': '🚛',
    'eaux_forets': '🌿', 'magistrature': '⚖️', 'gendarmerie': '🎖️',
    'administration': '🏛️',
  }[key] ?? '🎓';
}

class ConcourNom {
  static String get(String key) => {
    'police': 'Police Nationale', 'enaref': 'ENAREF', 'enseignement_primaire': 'ENEP',
    'enseignement_secondaire': 'ENS', 'sante': 'Santé Publique', 'douanes': 'Douanes',
    'eaux_forets': 'Eaux & Forêts', 'magistrature': 'Magistrature', 'gendarmerie': 'Gendarmerie',
    'administration': 'Administration',
  }[key] ?? 'Concours';
}
