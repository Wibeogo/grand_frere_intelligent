import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';

/// Splash screen avec vérification du token et redirection intelligente
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double>   _fadeAnim;
  late Animation<double>   _scaleAnim;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: const Duration(milliseconds: 1200),
      vsync: this,
    );
    _fadeAnim  = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeIn),
    );
    _scaleAnim = Tween<double>(begin: 0.8, end: 1).animate(
      CurvedAnimation(parent: _controller, curve: Curves.elasticOut),
    );
    _controller.forward();
    _checkAuthAndNavigate();
  }

  Future<void> _checkAuthAndNavigate() async {
    await Future.delayed(const Duration(milliseconds: 2500));

    if (!mounted) return;
    final auth = context.read<AuthProvider>();

    // Attendre que l'initialisation soit complète
    if (!auth.isInitialized) {
      await Future.delayed(const Duration(milliseconds: 1000));
    }

    if (!mounted) return;

    // Si une erreur grave empêche l'initialisation (ex: API injoignable mais token présent)
    if (auth.errorMessage != null && !auth.isLoggedIn) {
      // On ne fait rien ici pour que l'interface affiche l'erreur (modifiée plus bas)
      return; 
    }

    if (auth.isLoggedIn) {
      // Si pas de concours ciblé → aller à la sélection
      if (auth.user?.concoursCible == null || auth.user!.concoursCible!.isEmpty) {
        Navigator.pushReplacementNamed(context, '/concours');
      } else {
        Navigator.pushReplacementNamed(context, '/chat');
      }
    } else {
      Navigator.pushReplacementNamed(context, '/login');
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin:  Alignment.topLeft,
            end:    Alignment.bottomRight,
            colors: [Color(0xFF0D0B1F), Color(0xFF1A0A3B), Color(0xFF0D0B1F)],
          ),
        ),
        child: Center(
          child: FadeTransition(
            opacity: _fadeAnim,
            child: ScaleTransition(
              scale: _scaleAnim,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  // Logo
                  Container(
                    width:  120,
                    height: 120,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF5C35D9), Color(0xFF9B59B6)],
                        begin:  Alignment.topLeft,
                        end:    Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(30),
                      boxShadow: [
                        BoxShadow(
                          color:   const Color(0xFF5C35D9).withOpacity(0.4),
                          blurRadius: 30,
                          spreadRadius: 5,
                        ),
                      ],
                    ),
                    child: const Center(
                      child: Text('🎓', style: TextStyle(fontSize: 60)),
                    ),
                  ),
                  const SizedBox(height: 32),

                  // Titre
                  Text(
                    'Grand Frère',
                    style: Theme.of(context).textTheme.displayMedium?.copyWith(
                      foreground: Paint()
                        ..shader = const LinearGradient(
                          colors: [Color(0xFF5C35D9), Color(0xFFF0B429)],
                        ).createShader(const Rect.fromLTWH(0, 0, 300, 50)),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    'Intelligent',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: const Color(0xFFBBB5D8),
                      letterSpacing: 4,
                    ),
                  ),

                  const SizedBox(height: 16),
                  Text(
                    'Ton coach pour les concours burkinabè',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: const Color(0xFF6B6490),
                    ),
                    textAlign: TextAlign.center,
                  ),

                  const SizedBox(height: 60),

                  const SizedBox(height: 60),

                  // Indicateur de chargement ou erreur
                  Consumer<AuthProvider>(
                    builder: (context, auth, _) {
                      if (auth.isInitialized && auth.errorMessage != null && !auth.isLoggedIn) {
                        return Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: Colors.red.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.red.withOpacity(0.3)),
                          ),
                          child: Column(
                            children: [
                              const Icon(Icons.wifi_off, color: Colors.red, size: 32),
                              const SizedBox(height: 8),
                              Text(
                                auth.errorMessage!,
                                style: const TextStyle(color: Colors.red, fontSize: 14),
                                textAlign: TextAlign.center,
                              ),
                              const SizedBox(height: 16),
                              ElevatedButton(
                                onPressed: () {
                                  auth.initialize();
                                  _checkAuthAndNavigate();
                                },
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.red.withOpacity(0.2),
                                  foregroundColor: Colors.red,
                                ),
                                child: const Text('Réessayer'),
                                
                              ),
                              const SizedBox(height: 8),
                              TextButton(
                                onPressed: () {
                                  auth.logout();
                                  Navigator.pushReplacementNamed(context, '/login');
                                },
                                child: const Text('Aller à la connexion', style: TextStyle(color: Colors.white70)),
                              )
                            ],
                          ),
                        );
                      }
                      
                      return const SizedBox(
                        width:  40,
                        height: 40,
                        child: CircularProgressIndicator(
                          color: Color(0xFF5C35D9),
                          strokeWidth: 3,
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
