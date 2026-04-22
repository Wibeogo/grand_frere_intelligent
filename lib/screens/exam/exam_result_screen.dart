import 'package:flutter/material.dart';
import '../../models/exam_model.dart';

class ExamResultScreen extends StatelessWidget {
  const ExamResultScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final result = ModalRoute.of(context)!.settings.arguments as ExamResult;
    final pct    = result.percentage;
    final color  = pct >= 80 ? const Color(0xFF4CAF50)
                 : pct >= 60 ? const Color(0xFFF0B429)
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
                    begin: Alignment.topCenter, end: Alignment.bottomCenter,
                    colors: [color.withOpacity(0.3), const Color(0xFF0D0B1F)],
                  ),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const SizedBox(height: 40),
                    Text(pct >= 60 ? '✅' : '❌', style: const TextStyle(fontSize: 56)),
                    const SizedBox(height: 12),
                    Text('${pct.toStringAsFixed(1)}%',
                      style: TextStyle(color: color, fontSize: 52, fontWeight: FontWeight.w900)),
                    Text('${result.score} / ${result.totalPossible} points',
                      style: const TextStyle(color: Colors.white70, fontSize: 16)),
                    const SizedBox(height: 8),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 32),
                      child: Text(result.appreciation,
                        style: const TextStyle(color: Colors.white, fontSize: 14),
                        textAlign: TextAlign.center),
                    ),
                    if (result.durationTaken > 0)
                      Text('⏱ Durée : ${result.durationTaken} minutes',
                        style: const TextStyle(color: Colors.white54, fontSize: 12)),
                  ],
                ),
              ),
            ),
          ),

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
                            onPressed: () => Navigator.pushReplacementNamed(context, '/exam'),
                            icon: const Icon(Icons.refresh),
                            label: const Text('Nouvel examen'),
                          ),
                          OutlinedButton.icon(
                            onPressed: () => Navigator.pushNamedAndRemoveUntil(context, '/chat', (_) => false),
                            icon: const Icon(Icons.chat, color: Colors.white70),
                            label: const Text('Chat', style: TextStyle(color: Colors.white70)),
                            style: OutlinedButton.styleFrom(side: const BorderSide(color: Color(0xFF3A3060))),
                          ),
                        ],
                      ),
                    );
                  }

                  // Sections
                  final flatQuestions = result.sections
                      .expand((s) => s.questions.map((q) => (section: s.sectionTitle, q: q)))
                      .toList();

                  if (i - 1 >= flatQuestions.length) return null;
                  final item    = flatQuestions[i - 1];
                  final q       = item.q;
                  final isCorrect = q.isCorrect ?? false;

                  return Container(
                    margin:  const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color:        const Color(0xFF1A1730),
                      borderRadius: BorderRadius.circular(14),
                      border:       Border.all(
                        color: isCorrect ? const Color(0xFF4CAF50).withOpacity(0.4)
                            : const Color(0xFFE74C3C).withOpacity(0.4),
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(q.question, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 8),
                        if (q.userAnswer?.isNotEmpty == true)
                          Text('Ta réponse : ${q.userAnswer}',
                            style: const TextStyle(color: Color(0xFFBBB5D8), fontSize: 13)),
                        if (q.correctAnswer?.isNotEmpty == true)
                          Text('✅ ${q.correctAnswer}',
                            style: const TextStyle(color: Color(0xFF4CAF50), fontSize: 13)),
                        if (q.iaFeedback?.isNotEmpty == true) ...[
                          const SizedBox(height: 6),
                          Container(
                            padding:    const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color:        const Color(0xFF231E42),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text('💡 ${q.iaFeedback}',
                              style: const TextStyle(color: Color(0xFFBBB5D8), fontSize: 12)),
                          ),
                        ],
                        const SizedBox(height: 6),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            Text('${q.pointsEarned ?? 0}/${q.points} pts',
                              style: TextStyle(
                                color:      isCorrect ? const Color(0xFF4CAF50) : const Color(0xFFE74C3C),
                                fontWeight: FontWeight.w700, fontSize: 12,
                              )),
                          ],
                        ),
                      ],
                    ),
                  );
                },
                childCount: result.sections.expand((s) => s.questions).length + 1,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
