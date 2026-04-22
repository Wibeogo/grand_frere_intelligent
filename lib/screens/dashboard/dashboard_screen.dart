import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:percent_indicator/percent_indicator.dart';
import '../../providers/auth_provider.dart';

/// Tableau de bord : statistiques, scores, progression
class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  static const _primary = Color(0xFF5C35D9);
  static const _gold    = Color(0xFFF0B429);

  @override
  Widget build(BuildContext context) {
    final auth  = context.watch<AuthProvider>();
    final user  = auth.user;
    final stats = user?.stats;

    return Scaffold(
      backgroundColor: const Color(0xFF0D0B1F),
      appBar: AppBar(
        title: const Text('📊 Tableau de bord', style: TextStyle(fontWeight: FontWeight.w700)),
        backgroundColor: const Color(0xFF0D0B1F),
      ),
      body: RefreshIndicator(
        onRefresh: () => auth.refreshProfile(),
        color:     _primary,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Carte profil + statut
              _ProfileCard(user: user, auth: auth),
              const SizedBox(height: 16),

              // Statistiques rapides
              if (stats != null) ...[
                const Text('📈 Statistiques',
                  style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
                const SizedBox(height: 12),
                GridView.count(
                  crossAxisCount:       2,
                  shrinkWrap:           true,
                  physics:              const NeverScrollableScrollPhysics(),
                  mainAxisSpacing:      12,
                  crossAxisSpacing:     12,
                  childAspectRatio:     1.5,
                  children: [
                    _StatCard(label: 'Quiz passés',    value: '${stats.totalQuiz}',      icon: '🎯', color: _primary),
                    _StatCard(label: 'Score moyen',    value: '${stats.avgScore.toStringAsFixed(1)}%', icon: '⭐', color: _gold),
                    _StatCard(label: 'Examens blancs', value: '${stats.totalExams}',     icon: '📋', color: const Color(0xFF4CAF50)),
                    _StatCard(label: 'Messages envoyés',value: '${stats.totalMessages}', icon: '💬', color: const Color(0xFF9B59B6)),
                  ],
                ),
                const SizedBox(height: 24),
              ],

              // Score moyen en graphique circulaire
              if (stats != null && stats.avgScore > 0) ...[
                const Text('🎯 Score global',
                  style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
                const SizedBox(height: 12),
                Container(
                  padding:     const EdgeInsets.all(20),
                  decoration:  BoxDecoration(
                    color:        const Color(0xFF1A1730),
                    borderRadius: BorderRadius.circular(20),
                    border:       Border.all(color: const Color(0xFF3A3060)),
                  ),
                  child: Row(
                    children: [
                      CircularPercentIndicator(
                        radius:     60,
                        lineWidth:  12,
                        percent:    (stats.avgScore / 100).clamp(0.0, 1.0),
                        center:     Text(
                          '${stats.avgScore.toStringAsFixed(0)}%',
                          style: const TextStyle(
                            color:      Colors.white,
                            fontSize:   20,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        progressColor:    _gold,
                        backgroundColor:  const Color(0xFF3A3060),
                        circularStrokeCap:CircularStrokeCap.round,
                      ),
                      const SizedBox(width: 20),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              stats.avgScore >= 80 ? '🏆 Excellent !'
                                  : stats.avgScore >= 60 ? '👍 Bien !'
                                  : stats.avgScore >= 40 ? '📚 À améliorer'
                                  : '💪 Continue !',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Moyenne sur ${stats.totalQuiz} quiz. Continue à t\'entraîner pour progresser !',
                              style: const TextStyle(color: Color(0xFF9D96C0), fontSize: 13),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
              ],

              // Badges / Achievements
              const Text('🏅 Badges',
                style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
              const SizedBox(height: 12),
              _BadgesSection(stats: stats),
              const SizedBox(height: 24),

              // Actions rapides
              const Text('⚡ Actions rapides',
                style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
              const SizedBox(height: 12),
              _QuickActions(),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProfileCard extends StatelessWidget {
  final dynamic user;
  final AuthProvider auth;
  const _ProfileCard({this.user, required this.auth});

  static const _primary = Color(0xFF5C35D9);
  static const _gold    = Color(0xFFF0B429);

  @override
  Widget build(BuildContext context) {
    final isPremium    = auth.isPremium;
    final daysLeft     = user?.trialDaysLeft ?? user?.subscriptionDaysLeft ?? 0;
    final statusLabel  = user?.subscriptionStatus == 'trial'
        ? '🎁 Essai gratuit – $daysLeft jour(s) restant(s)'
        : user?.subscriptionStatus == 'premium'
            ? '⭐ Premium – $daysLeft jour(s) restant(s)'
            : '🔒 Abonnement expiré';

    return Container(
      padding:     const EdgeInsets.all(20),
      decoration:  BoxDecoration(
        gradient:     const LinearGradient(
          colors:  [Color(0xFF231E42), Color(0xFF1A1730)],
          begin:   Alignment.topLeft,
          end:     Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        border:       Border.all(color: const Color(0xFF3A3060)),
      ),
      child: Row(
        children: [
          // Avatar
          Container(
            width:  60, height: 60,
            decoration: BoxDecoration(
              gradient:     const LinearGradient(colors: [_primary, Color(0xFF9B59B6)]),
              shape:        BoxShape.circle,
            ),
            child: Center(
              child: Text(
                (user?.firstName ?? 'C').substring(0, 1).toUpperCase(),
                style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w800),
              ),
            ),
          ),
          const SizedBox(width: 16),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(user?.displayName ?? 'Candidat',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 17)),
                const SizedBox(height: 4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color:        isPremium ? _gold.withOpacity(0.15) : const Color(0xFF231E42),
                    borderRadius: BorderRadius.circular(20),
                    border:       Border.all(color: isPremium ? _gold.withOpacity(0.5) : const Color(0xFF3A3060)),
                  ),
                  child: Text(statusLabel,
                    style: TextStyle(
                      color:      isPremium ? _gold : const Color(0xFF9D96C0),
                      fontSize:   11,
                      fontWeight: FontWeight.w600,
                    )),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label, value, icon;
  final Color color;
  const _StatCard({required this.label, required this.value, required this.icon, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding:    const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color:        const Color(0xFF1A1730),
        borderRadius: BorderRadius.circular(16),
        border:       Border.all(color: color.withOpacity(0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(icon, style: const TextStyle(fontSize: 24)),
          const Spacer(),
          Text(value,
            style: TextStyle(color: color, fontSize: 22, fontWeight: FontWeight.w800)),
          Text(label,
            style: const TextStyle(color: Color(0xFF9D96C0), fontSize: 11)),
        ],
      ),
    );
  }
}

class _BadgesSection extends StatelessWidget {
  final dynamic stats;
  const _BadgesSection({this.stats});

  @override
  Widget build(BuildContext context) {
    final badges = [
      if ((stats?.totalQuiz  ?? 0) >= 1)  ('🎯', 'Premier quiz',   'Tu as passé ton 1er quiz !'),
      if ((stats?.totalQuiz  ?? 0) >= 10) ('🔥', 'Assidu',         '10 quiz complétés'),
      if ((stats?.avgScore   ?? 0) >= 80) ('⭐', 'Brillant',        '80% de moyenne'),
      if ((stats?.totalExams ?? 0) >= 1)  ('📋', 'Examiné',        'Examen blanc complété'),
      if ((stats?.totalMessages ?? 0) >= 50)('💬','Grand dialogueur','50 messages envoyés'),
    ];

    if (badges.isEmpty) {
      return Container(
        padding:     const EdgeInsets.all(16),
        decoration:  BoxDecoration(color: const Color(0xFF1A1730), borderRadius: BorderRadius.circular(16)),
        child: const Text(
          '🎯 Commence à t\'entraîner pour débloquer tes badges !',
          style: TextStyle(color: Color(0xFF9D96C0)),
        ),
      );
    }

    return Wrap(
      spacing: 10, runSpacing: 10,
      children: badges.map((b) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color:        const Color(0xFF231E42),
          borderRadius: BorderRadius.circular(12),
          border:       Border.all(color: const Color(0xFFF0B429).withOpacity(0.3)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(b.$1, style: const TextStyle(fontSize: 24)),
            const SizedBox(height: 4),
            Text(b.$2, style: const TextStyle(color: Color(0xFFF0B429), fontSize: 11, fontWeight: FontWeight.w700)),
          ],
        ),
      )).toList(),
    );
  }
}

class _QuickActions extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final actions = [
      (icon: '💬', label: 'Poser une question', route: '/chat',   color: const Color(0xFF5C35D9)),
      (icon: '🎯', label: 'Faire un quiz',       route: '/quiz',   color: const Color(0xFF9B59B6)),
      (icon: '📋', label: 'Examen blanc',        route: '/exam',   color: const Color(0xFF4CAF50)),
      (icon: '🏫', label: 'Changer concours',    route: '/concours', color: const Color(0xFFF0B429)),
    ];

    return GridView.count(
      crossAxisCount:   2,
      shrinkWrap:       true,
      physics:          const NeverScrollableScrollPhysics(),
      mainAxisSpacing:  12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.8,
      children: actions.map((a) => GestureDetector(
        onTap: () => Navigator.pushNamed(context, a.route),
        child: Container(
          padding:    const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color:        a.color.withOpacity(0.1),
            borderRadius: BorderRadius.circular(16),
            border:       Border.all(color: a.color.withOpacity(0.3)),
          ),
          child: Row(
            children: [
              Text(a.icon, style: const TextStyle(fontSize: 24)),
              const SizedBox(width: 10),
              Expanded(
                child: Text(a.label,
                  style: TextStyle(color: a.color, fontWeight: FontWeight.w700, fontSize: 13)),
              ),
            ],
          ),
        ),
      )).toList(),
    );
  }
}
