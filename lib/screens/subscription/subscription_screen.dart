import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../providers/auth_provider.dart';
import '../../providers/subscription_provider.dart';
import '../../config/api_config.dart';

/// Écran d'abonnement premium
class SubscriptionScreen extends StatelessWidget {
  const SubscriptionScreen({super.key});

  static const _primary = Color(0xFF5C35D9);
  static const _gold    = Color(0xFFF0B429);

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final sub  = context.watch<SubscriptionProvider>();
    final user = auth.user;

    return Scaffold(
      backgroundColor: const Color(0xFF0D0B1F),
      appBar: AppBar(
        title: const Text('⭐ Mon abonnement', style: TextStyle(fontWeight: FontWeight.w700)),
        backgroundColor: const Color(0xFF0D0B1F),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            // Statut actuel
            _StatusCard(user: user),
            const SizedBox(height: 24),

            // Offre premium
            if (!(user?.isPremium ?? false) || (user?.isExpired ?? true)) ...[
              const Text(
                '🎖️ Passe au Premium',
                style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              const Text(
                'Un seul abonnement pour tout préparer au Burkina Faso',
                style: TextStyle(color: Color(0xFF9D96C0)),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 20),

              // Carte de prix
              Container(
                padding:     const EdgeInsets.all(24),
                decoration:  BoxDecoration(
                  gradient:     const LinearGradient(
                    colors: [Color(0xFF231E42), Color(0xFF1A1730)],
                    begin:  Alignment.topLeft,
                    end:    Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: _gold.withOpacity(0.5), width: 2),
                ),
                child: Column(
                  children: [
                    const Text(
                      'Mensuel',
                      style: TextStyle(color: Color(0xFF9D96C0), fontSize: 14, fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          '${ApiConfig.subscriptionPrice}',
                          style: TextStyle(color: Color(0xFFF0B429), fontSize: 48, fontWeight: FontWeight.w900),
                        ),
                        const Padding(
                          padding: EdgeInsets.only(top: 12),
                          child: Text(
                            ' FCFA',
                            style: TextStyle(color: Color(0xFF9D96C0), fontSize: 16),
                          ),
                        ),
                      ],
                    ),
                    const Text('/mois', style: TextStyle(color: Color(0xFF6B6490))),
                    const SizedBox(height: 20),

                    // Features
                    ...[
                      '✅ Chat illimité avec le Grand Frère',
                      '✅ Quiz illimités personnalisés par concours',
                      '✅ Examens blancs complets avec timer',
                      '✅ Correction de photos de sujets',
                      '✅ Suivi de progression et badges',
                      '✅ Notifications quotidiennes (email + SMS)',
                      '✅ Accès aux 10 concours burkinabè',
                    ].map((f) => Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        children: [
                          const SizedBox(width: 8),
                          Expanded(child: Text(f, style: const TextStyle(color: Color(0xFFBBB5D8), fontSize: 14))),
                        ],
                      ),
                    )),
                    const SizedBox(height: 24),

                    // Bouton paiement
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: sub.isLoading ? null : () => _initiatePayment(context),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: _gold,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                        ),
                        child: sub.isLoading
                            ? const SizedBox(
                                width: 24, height: 24,
                                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                              )
                            : const Text(
                                '💳 Payer avec Orange Money / Moov Money',
                                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 15),
                                textAlign: TextAlign.center,
                              ),
                      ),
                    ),

                    if (sub.error != null) ...[
                      const SizedBox(height: 12),
                      Text(sub.error!, style: const TextStyle(color: Colors.redAccent, fontSize: 13)),
                    ],
                  ],
                ),
              ),

              const SizedBox(height: 16),
              const Text(
                '🔒 Paiement sécurisé via Senfenico\nOrange Money & Moov Money acceptés',
                style: TextStyle(color: Color(0xFF6B6490), fontSize: 12),
                textAlign: TextAlign.center,
              ),
            ] else ...[
              // Déjà premium
              Container(
                padding:     const EdgeInsets.all(24),
                decoration:  BoxDecoration(
                  color:        const Color(0xFF4CAF50).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(20),
                  border:       Border.all(color: const Color(0xFF4CAF50).withOpacity(0.3)),
                ),
                child: Column(
                  children: [
                    const Text('✅', style: TextStyle(fontSize: 44)),
                    const SizedBox(height: 12),
                    const Text('Abonnement actif !',
                      style: TextStyle(color: Color(0xFF4CAF50), fontSize: 20, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    Text(
                      user?.subscriptionStatus == 'trial'
                          ? '${user!.trialDaysLeft} jour(s) d\'essai restant(s)'
                          : '${user!.subscriptionDaysLeft} jour(s) restant(s)',
                      style: const TextStyle(color: Colors.white70),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _initiatePayment(BuildContext context) async {
    final sub = context.read<SubscriptionProvider>();
    final url = await sub.initiatePayment();
    if (url != null && context.mounted) {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => _PaymentWebView(
            url:         url,
            onSuccess:   () {
              final auth = context.read<AuthProvider>();
              sub.onPaymentSuccess(auth);
              Navigator.pop(context);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('🎉 Paiement réussi ! Bienvenue en Premium !'),
                  backgroundColor: Color(0xFF4CAF50),
                ),
              );
            },
          ),
        ),
      );
    }
  }
}

class _StatusCard extends StatelessWidget {
  final dynamic user;
  const _StatusCard({this.user});

  @override
  Widget build(BuildContext context) {
    final isPremium = user?.isPremium ?? false;
    final status    = user?.subscriptionStatus ?? 'expired';

    return Container(
      padding:     const EdgeInsets.all(16),
      decoration:  BoxDecoration(
        color:        const Color(0xFF1A1730),
        borderRadius: BorderRadius.circular(16),
        border:       Border.all(color: const Color(0xFF3A3060)),
      ),
      child: Row(
        children: [
          Text(isPremium ? '⭐' : '🔒', style: const TextStyle(fontSize: 32)),
          const SizedBox(width: 16),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                status == 'trial'   ? 'Période d\'essai'
                    : status == 'premium' ? 'Abonnement Premium'
                    : 'Aucun abonnement',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16),
              ),
              Text(
                status == 'trial'
                    ? '${user?.trialDaysLeft ?? 0} jour(s) restant(s)'
                    : status == 'premium'
                        ? '${user?.subscriptionDaysLeft ?? 0} jour(s) restant(s)'
                        : 'Abonnez-vous pour 2800 FCFA/mois',
                style: const TextStyle(color: Color(0xFF9D96C0), fontSize: 13),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// WebView pour le paiement Senfenico
class _PaymentWebView extends StatefulWidget {
  final String url;
  final VoidCallback onSuccess;
  const _PaymentWebView({required this.url, required this.onSuccess});

  @override
  State<_PaymentWebView> createState() => _PaymentWebViewState();
}

class _PaymentWebViewState extends State<_PaymentWebView> {
  late WebViewController _controller;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(NavigationDelegate(
        onPageStarted: (_) => setState(() => _loading = true),
        onPageFinished: (url) {
          setState(() => _loading = false);
          // Détecter la redirection de succès
          if (url.contains('payment_success') || url.contains('success=true')) {
            widget.onSuccess();
          }
        },
      ))
      ..loadRequest(Uri.parse(widget.url));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Paiement sécurisé'),
        backgroundColor: const Color(0xFF0D0B1F),
        leading: IconButton(
          onPressed: () => Navigator.pop(context),
          icon: const Icon(Icons.close),
        ),
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_loading)
            const Center(child: CircularProgressIndicator(color: Color(0xFF5C35D9))),
        ],
      ),
    );
  }
}
