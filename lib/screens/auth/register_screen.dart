import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';

/// Écran d'inscription
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey      = GlobalKey<FormState>();
  final _nameCtrl     = TextEditingController();
  final _emailCtrl    = TextEditingController();
  final _phoneCtrl    = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _confirmCtrl  = TextEditingController();
  bool _obscure = true;
  String? _errorMsg;

  static const _primary  = Color(0xFF5C35D9);
  static const _gold     = Color(0xFFF0B429);

  @override
  void dispose() {
    _nameCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _passwordCtrl.dispose();
    _confirmCtrl.dispose();
    super.dispose();
  }

  Future<void> _handleRegister() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _errorMsg = null);

    final auth  = context.read<AuthProvider>();
    final error = await auth.register(
      email:    _emailCtrl.text.trim(),
      password: _passwordCtrl.text.trim(),
      phone:    _phoneCtrl.text.trim(),
      fullName: _nameCtrl.text.trim(),
    );

    if (!mounted) return;
    if (error != null) {
      setState(() => _errorMsg = error);
    } else {
      Navigator.pushReplacementNamed(context, '/concours');
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
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
            child: Column(
              children: [
                // Header
                Row(
                  children: [
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.arrow_back_ios, color: Colors.white),
                    ),
                    const Expanded(
                      child: Text(
                        'Créer un compte',
                        style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700),
                        textAlign: TextAlign.center,
                      ),
                    ),
                    const SizedBox(width: 48),
                  ],
                ),

                const SizedBox(height: 24),

                // Badge essai gratuit
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [_gold.withOpacity(0.15), _primary.withOpacity(0.15)],
                    ),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: _gold.withOpacity(0.4)),
                  ),
                  child: const Row(
                    children: [
                      Text('🎁', style: TextStyle(fontSize: 32)),
                      SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '✨ 3 jours GRATUITS !',
                              style: TextStyle(
                                color: Color(0xFFF0B429),
                                fontWeight: FontWeight.w800, fontSize: 15,
                              ),
                            ),
                            Text(
                              'Accès complet dès l\'inscription. Sans carte bancaire.',
                              style: TextStyle(color: Color(0xFF9D96C0), fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 28),

                // Formulaire
                Form(
                  key: _formKey,
                  child: Column(
                    children: [
                      _buildField(_nameCtrl, 'Nom complet', Icons.person_outline,
                          validator: (v) => v == null || v.length < 2 ? 'Nom requis' : null),
                      const SizedBox(height: 14),
                      _buildField(_emailCtrl, 'Email', Icons.email_outlined,
                          keyboardType: TextInputType.emailAddress,
                          validator: (v) => v == null || !v.contains('@') ? 'Email invalide' : null),
                      const SizedBox(height: 14),
                      _buildField(_phoneCtrl, 'Téléphone (ex: 70123456)', Icons.phone_outlined,
                          keyboardType: TextInputType.phone,
                          validator: (v) {
                            final digits = v?.replaceAll(RegExp(r'\D'), '') ?? '';
                            return digits.length < 8 ? 'Numéro invalide' : null;
                          }),
                      const SizedBox(height: 14),
                      _buildField(_passwordCtrl, 'Mot de passe', Icons.lock_outline,
                          obscure:   _obscure,
                          hasSuffix: true,
                          validator: (v) => v == null || v.length < 6 ? 'Minimum 6 caractères' : null),
                      const SizedBox(height: 14),
                      _buildField(_confirmCtrl, 'Confirmer le mot de passe', Icons.lock_outline,
                          obscure: _obscure,
                          validator: (v) => v != _passwordCtrl.text ? 'Les mots de passe ne correspondent pas' : null),

                      const SizedBox(height: 12),

                      if (_errorMsg != null)
                        Container(
                          padding: const EdgeInsets.all(12),
                          margin: const EdgeInsets.only(bottom: 8),
                          decoration: BoxDecoration(
                            color: Colors.red.withOpacity(0.1),
                            border: Border.all(color: Colors.red.withOpacity(0.3)),
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

                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: auth.isLoading ? null : _handleRegister,
                          child: auth.isLoading
                              ? const SizedBox(
                                  width:  24, height: 24,
                                  child:  CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                                )
                              : const Text('Créer mon compte & démarrer l\'essai'),
                        ),
                      ),

                      const SizedBox(height: 16),

                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Text('Déjà inscrit ? ', style: TextStyle(color: Color(0xFF9D96C0))),
                          GestureDetector(
                            onTap: () => Navigator.pop(context),
                            child: const Text(
                              'Se connecter',
                              style: TextStyle(color: _gold, fontWeight: FontWeight.w700),
                            ),
                          ),
                        ],
                      ),

                      const SizedBox(height: 12),
                      Text(
                        'En créant un compte, tu acceptes nos conditions d\'utilisation.',
                        style: const TextStyle(color: Color(0xFF6B6490), fontSize: 11),
                        textAlign: TextAlign.center,
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

  Widget _buildField(
    TextEditingController ctrl,
    String hint,
    IconData icon, {
    TextInputType keyboardType = TextInputType.text,
    bool obscure     = false,
    bool hasSuffix   = false,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller:   ctrl,
      obscureText:  obscure,
      keyboardType: keyboardType,
      style:        const TextStyle(color: Colors.white),
      decoration:   InputDecoration(
        hintText:   hint,
        prefixIcon: Icon(icon, color: const Color(0xFF6B6490)),
        suffixIcon: hasSuffix
            ? IconButton(
                icon: Icon(
                  _obscure ? Icons.visibility_off : Icons.visibility,
                  color: const Color(0xFF6B6490),
                ),
                onPressed: () => setState(() => _obscure = !_obscure),
              )
            : null,
      ),
      validator:    validator,
    );
  }
}
