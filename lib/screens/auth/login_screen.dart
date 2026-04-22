import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';

/// Écran de connexion
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey     = GlobalKey<FormState>();
  final _emailCtrl   = TextEditingController();
  final _passwordCtrl= TextEditingController();
  bool _obscurePassword = true;
  String? _errorMsg;

  static const _primary    = Color(0xFF5C35D9);
  static const _gold       = Color(0xFFF0B429);
  static const _bgDark     = Color(0xFF0D0B1F);
  static const _cardDark   = Color(0xFF231E42);

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _errorMsg = null);

    final auth    = context.read<AuthProvider>();
    final error   = await auth.login(
      email:    _emailCtrl.text.trim(),
      password: _passwordCtrl.text.trim(),
    );

    if (!mounted) return;

    if (error != null) {
      setState(() => _errorMsg = error);
    } else {
      final user = auth.user;
      if (user?.concoursCible == null || user!.concoursCible!.isEmpty) {
        Navigator.pushReplacementNamed(context, '/concours');
      } else {
        Navigator.pushReplacementNamed(context, '/chat');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin:  Alignment.topLeft,
            end:    Alignment.bottomRight,
            colors: [Color(0xFF0D0B1F), Color(0xFF1A0A3B), Color(0xFF0D0B1F)],
          ),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 40),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Header
                Center(
                  child: Column(
                    children: [
                      Container(
                        width:  80,
                        height: 80,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [_primary, Color(0xFF9B59B6)],
                          ),
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color:   _primary.withOpacity(0.4),
                              blurRadius: 20,
                              spreadRadius: 2,
                            ),
                          ],
                        ),
                        child: const Center(
                          child: Text('🎓', style: TextStyle(fontSize: 40)),
                        ),
                      ),
                      const SizedBox(height: 20),
                      const Text(
                        'Bon retour ! 👋',
                        style: TextStyle(
                          color:      Colors.white,
                          fontSize:   28,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Connecte-toi pour continuer ta préparation',
                        style: TextStyle(color: Color(0xFF9D96C0), fontSize: 14),
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 40),

                // Formulaire
                Form(
                  key: _formKey,
                  child: Column(
                    children: [
                      // Email
                      TextFormField(
                        controller:   _emailCtrl,
                        keyboardType: TextInputType.emailAddress,
                        style:        const TextStyle(color: Colors.white),
                        decoration:   _inputDecoration('Email', Icons.email_outlined),
                        validator:    (v) => v == null || !v.contains('@')
                            ? 'Email invalide'
                            : null,
                      ),
                      const SizedBox(height: 16),

                      // Mot de passe
                      TextFormField(
                        controller:  _passwordCtrl,
                        obscureText: _obscurePassword,
                        style:       const TextStyle(color: Colors.white),
                        decoration:  _inputDecoration('Mot de passe', Icons.lock_outline).copyWith(
                          suffixIcon: IconButton(
                            icon: Icon(
                              _obscurePassword ? Icons.visibility_off : Icons.visibility,
                              color: const Color(0xFF6B6490),
                            ),
                            onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                          ),
                        ),
                        validator: (v) => v == null || v.length < 6
                            ? 'Au moins 6 caractères'
                            : null,
                      ),

                      const SizedBox(height: 12),

                      // Message d'erreur
                      if (_errorMsg != null)
                        Container(
                          padding: const EdgeInsets.all(12),
                          margin:  const EdgeInsets.only(bottom: 8),
                          decoration: BoxDecoration(
                            color:        Colors.red.withOpacity(0.1),
                            border:       Border.all(color: Colors.red.withOpacity(0.3)),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.error_outline, color: Colors.red, size: 18),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(_errorMsg!, style: const TextStyle(color: Colors.red, fontSize: 13)),
                              ),
                            ],
                          ),
                        ),

                      const SizedBox(height: 24),

                      // Bouton connexion
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed:    auth.isLoading ? null : _handleLogin,
                          child: auth.isLoading
                              ? const SizedBox(
                                  width: 24, height: 24,
                                  child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                                )
                              : const Text('Se connecter'),
                        ),
                      ),

                      const SizedBox(height: 24),

                      // Lien inscription
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Text('Pas encore de compte ? ',
                            style: TextStyle(color: Color(0xFF9D96C0))),
                          GestureDetector(
                            onTap: () => Navigator.pushNamed(context, '/register'),
                            child: const Text(
                              'S\'inscrire',
                              style: TextStyle(
                                color:      _gold,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 40),

                // Badge essai gratuit
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [_gold.withOpacity(0.1), _primary.withOpacity(0.1)],
                    ),
                    border:       Border.all(color: _gold.withOpacity(0.3)),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Row(
                    children: [
                      Text('🎁', style: TextStyle(fontSize: 28)),
                      SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '3 jours d\'essai gratuit',
                              style: TextStyle(
                                color:      Color(0xFFF0B429),
                                fontWeight: FontWeight.w700,
                                fontSize:   15,
                              ),
                            ),
                            Text(
                              'Accès complet à l\'IA, quiz et examens blancs sans carte bancaire.',
                              style: TextStyle(color: Color(0xFF9D96C0), fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(String hint, IconData icon) {
    return InputDecoration(
      hintText:  hint,
      prefixIcon:Icon(icon, color: const Color(0xFF6B6490)),
    );
  }
}
