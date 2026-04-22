import 'package:flutter/material.dart';
import '../../models/quiz_model.dart';

/// Écran de résultats du quiz
class QuizResultScreen extends StatelessWidget {
  const QuizResultScreen({super.key});

  static const _primary = Color(0xFF5C35D9);
  static const _gold    = Color(0xFFF0B429);

  @override
  Widget build(BuildContext context) {
    final result = ModalRoute.of(context)!.settings.arguments as QuizResult;
    final pct    = result.percentage;

    final Color scoreColor = pct >= 80
        ? const Color(0xFF4CAF50)
        : pct >= 60
            ? _gold
            : const Color(0xFFE74C3C);

    return Scaffold(
      backgroundColor: const Color(0xFF0D0B1F),
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 280,
            pinned: true,
            backgroundColor: const Color(0xFF0D0B1F),
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin:  Alignment.topCenter,
                    end:    Alignment.bottomCenter,
                    colors: [scoreColor.withOpacity(0.3), const Color(0xFF0D0B1F)],
                  ),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const SizedBox(height: 40),
                    Text(
                      pct >= 80 ? '🏆' : pct >= 60 ? '👍' : pct >= 40 ? '📚' : '💪',
                      style: const TextStyle(fontSize: 56),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      '$pct%',
                      style: TextStyle(
                        color:      scoreColor,
                        fontSize:   56,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      '${result.score} / ${result.total} bonnes réponses',
                      style: const TextStyle(color: Colors.white70, fontSize: 16),
                    ),
                    const SizedBox(height: 8),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 40),
                      child: Text(
                        result.appreciation,
                        style: const TextStyle(color: Colors.white, fontSize: 14),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // Correction détaillée
          SliverPadding(
            padding: const EdgeInsets.all(16),
            sliver: SliverList(
              delegate: SliverChildBuilderDelegate(
                (ctx, i) {
                  if (i == 0) {
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: [
                          ElevatedButton.icon(
                            onPressed: () => Navigator.pushReplacementNamed(context, '/quiz'),
                            icon:  const Icon(Icons.refresh),
                            label: const Text('Nouveau quiz'),
                          ),
                          OutlinedButton.icon(
                            onPressed: () => Navigator.pushNamedAndRemoveUntil(
                              context, '/chat', (_) => false),
                            icon:  const Icon(Icons.chat, color: Colors.white70),
                            label: const Text('Chat', style: TextStyle(color: Colors.white70)),
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: Color(0xFF3A3060)),
                            ),
                          ),
                        ],
                      ),
                    );
                  }

                  final detail = result.details[i - 1];
                  final isOk   = detail.isCorrect == true;

                  return Container(
                    margin:  const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color:        const Color(0xFF1A1730),
                      borderRadius: BorderRadius.circular(16),
                      border:       Border.all(
                        color: isOk ? const Color(0xFF4CAF50).withOpacity(0.4)
                            : const Color(0xFFE74C3C).withOpacity(0.4),
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              isOk ? Icons.check_circle : Icons.cancel,
                              color: isOk ? const Color(0xFF4CAF50) : const Color(0xFFE74C3C),
                              size: 20,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              'Q${i}',
                              style: const TextStyle(color: Colors.white54, fontSize: 12),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          detail.question,
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            const Text('Ta réponse : ', style: TextStyle(color: Colors.white54, fontSize: 13)),
                            Text(
                              detail.userAnswer ?? '—',
                              style: TextStyle(
                                color:      isOk ? const Color(0xFF4CAF50) : const Color(0xFFE74C3C),
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                        if (!isOk) ...[
                          Row(
                            children: [
                              const Text('Bonne réponse : ', style: TextStyle(color: Colors.white54, fontSize: 13)),
                              Text(
                                detail.correctAnswer ?? '—',
                                style: const TextStyle(color: Color(0xFF4CAF50), fontWeight: FontWeight.w700),
                              ),
                            ],
                          ),
                        ],
                        if (detail.explanation?.isNotEmpty == true) ...[
                          const SizedBox(height: 8),
                          Container(
                            padding:    const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color:        const Color(0xFF231E42),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              '💡 ${detail.explanation}',
                              style: const TextStyle(color: Color(0xFFBBB5D8), fontSize: 13),
                            ),
                          ),
                        ],
                      ],
                    ),
                  );
                },
                childCount: result.details.length + 1,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
