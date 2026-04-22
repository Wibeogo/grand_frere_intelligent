import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../models/quiz_model.dart';
import '../../providers/auth_provider.dart';
import '../../services/quiz_service.dart';
import '../../services/chat_service.dart';
import 'quiz_result_screen.dart';

/// Écran de quiz interactif avec timer et animations
class QuizScreen extends StatefulWidget {
  const QuizScreen({super.key});
  @override
  State<QuizScreen> createState() => _QuizScreenState();
}

class _QuizScreenState extends State<QuizScreen> with TickerProviderStateMixin {
  QuizModel? _quiz;
  bool _loading        = true;
  String? _error;
  int _currentIndex    = 0;
  final Map<int, String> _userAnswers = {};
  late AnimationController _slideCtrl;
  late Animation<Offset>   _slideAnim;

  static const _primary = Color(0xFF5C35D9);
  static const _gold    = Color(0xFFF0B429);
  static const _correct = Color(0xFF4CAF50);
  static const _wrong   = Color(0xFFE74C3C);

  @override
  void initState() {
    super.initState();
    _slideCtrl = AnimationController(
      duration: const Duration(milliseconds: 400), vsync: this,
    );
    _slideAnim = Tween<Offset>(begin: const Offset(1, 0), end: Offset.zero)
        .animate(CurvedAnimation(parent: _slideCtrl, curve: Curves.easeOut));
    _loadQuiz();
  }

  Future<void> _loadQuiz() async {
    final auth = context.read<AuthProvider>();
    setState(() { _loading = true; _error = null; });

    try {
      final quiz = await QuizService.generateQuiz(
        category:    auth.user?.concoursCible,
        nbQuestions: 10,
      );
      setState(() { _quiz = quiz; _loading = false; });
      _slideCtrl.forward();
    } on SubscriptionRequiredException {
      setState(() { _error = 'Abonnement requis.'; _loading = false; });
    } on ApiException catch (e) {
      setState(() { _error = e.message; _loading = false; });
    }
  }

  void _selectAnswer(String option) {
    if (_currentIndex >= (_quiz?.questions.length ?? 0)) return;
    final qId = _quiz!.questions[_currentIndex].id;
    setState(() => _userAnswers[qId] = option);
  }

  void _nextQuestion() {
    if (_currentIndex < (_quiz!.questions.length - 1)) {
      _slideCtrl.reset();
      setState(() => _currentIndex++);
      _slideCtrl.forward();
    }
  }

  void _prevQuestion() {
    if (_currentIndex > 0) {
      _slideCtrl.reset();
      setState(() => _currentIndex--);
      _slideCtrl.forward();
    }
  }

  Future<void> _submitQuiz() async {
    if (_quiz == null) return;
    final answersStr = _userAnswers.map((k, v) => MapEntry(k.toString(), v));

    showDialog(
      context:    context,
      barrierDismissible: false,
      builder:    (_) => const Center(child: CircularProgressIndicator(color: _primary)),
    );

    try {
      final result = await QuizService.submitQuiz(
        quizDataEncoded: _quiz!.quizDataEncoded,
        quizToken:       _quiz!.quizToken,
        answers:         answersStr,
        category:        _quiz!.category,
      );

      if (!mounted) return;
      Navigator.pop(context); // Fermer le dialog de chargement
      Navigator.pushReplacementNamed(
        context, '/quiz-result',
        arguments: result,
      );
    } catch (e) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Erreur : $e'), backgroundColor: _wrong),
        );
      }
    }
  }

  @override
  void dispose() {
    _slideCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return _buildLoading();
    if (_error != null) return _buildError();
    if (_quiz == null) return const SizedBox();

    final q       = _quiz!.questions[_currentIndex];
    final answered= _userAnswers[q.id];
    final progress= (_currentIndex + 1) / _quiz!.nbQuestions;

    return Scaffold(
      backgroundColor: const Color(0xFF0D0B1F),
      appBar: AppBar(
        title: Text('Quiz – ${_quiz!.quizTitle}',
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
        backgroundColor: const Color(0xFF0D0B1F),
        actions: [
          TextButton(
            onPressed: () => _showSubmitDialog(),
            child: const Text('Terminer', style: TextStyle(color: _gold)),
          ),
        ],
      ),
      body: Column(
        children: [
          // Barre de progression
          LinearProgressIndicator(
            value:           progress,
            backgroundColor: const Color(0xFF231E42),
            valueColor:      const AlwaysStoppedAnimation(_primary),
            minHeight:       4,
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Question ${_currentIndex + 1}/${_quiz!.nbQuestions}',
                  style: const TextStyle(color: Color(0xFF9D96C0), fontSize: 13),
                ),
                _DifficultyBadge(q.difficulty),
                Text(
                  '${_userAnswers.length} répondu(s)',
                  style: const TextStyle(color: Color(0xFF9D96C0), fontSize: 13),
                ),
              ],
            ),
          ),

          // Question + options
          Expanded(
            child: SlideTransition(
              position: _slideAnim,
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Column(
                  children: [
                    // Carte question
                    Container(
                      width:   double.infinity,
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [const Color(0xFF231E42), const Color(0xFF1A1730)],
                        ),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: const Color(0xFF3A3060)),
                      ),
                      child: Text(
                        q.question,
                        style: const TextStyle(
                          color: Colors.white, fontSize: 17, fontWeight: FontWeight.w600, height: 1.5,
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),

                    // Options
                    ...q.options.entries.map((e) {
                      final isSelected = answered == e.key;
                      return GestureDetector(
                        onTap: () => _selectAnswer(e.key),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          margin:   const EdgeInsets.only(bottom: 12),
                          padding:  const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color:  isSelected ? _primary.withOpacity(0.2) : const Color(0xFF231E42),
                            border: Border.all(
                              color: isSelected ? _primary : const Color(0xFF3A3060),
                              width: isSelected ? 2 : 1,
                            ),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Row(
                            children: [
                              Container(
                                width:  36, height: 36,
                                decoration: BoxDecoration(
                                  color:  isSelected ? _primary : const Color(0xFF3A3060),
                                  shape:  BoxShape.circle,
                                ),
                                child: Center(
                                  child: Text(
                                    e.key,
                                    style: TextStyle(
                                      color:      Colors.white,
                                      fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Text(
                                  e.value,
                                  style: TextStyle(
                                    color:      isSelected ? Colors.white : const Color(0xFFBBB5D8),
                                    fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
                                  ),
                                ),
                              ),
                              if (isSelected)
                                const Icon(Icons.check_circle, color: _primary, size: 20),
                            ],
                          ),
                        ),
                      );
                    }),
                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
          ),

          // Navigation
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _currentIndex > 0 ? _prevQuestion : null,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.white,
                      side:            const BorderSide(color: Color(0xFF3A3060)),
                    ),
                    child: const Text('← Précédent'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _currentIndex < _quiz!.nbQuestions - 1
                      ? ElevatedButton(
                          onPressed: answered != null ? _nextQuestion : null,
                          child:     const Text('Suivant →'),
                        )
                      : ElevatedButton(
                          onPressed: _userAnswers.length == _quiz!.nbQuestions
                              ? _submitQuiz
                              : null,
                          style: ElevatedButton.styleFrom(backgroundColor: _gold),
                          child: const Text('✅ Soumettre'),
                        ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _showSubmitDialog() {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: const Color(0xFF231E42),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Soumettre le quiz ?', style: TextStyle(color: Colors.white)),
        content: Text(
          'Tu as répondu à ${_userAnswers.length}/${_quiz!.nbQuestions} questions.\n'
          'Soumettre maintenant ?',
          style: const TextStyle(color: Color(0xFF9D96C0)),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Continuer'),
          ),
          ElevatedButton(
            onPressed: () { Navigator.pop(context); _submitQuiz(); },
            child: const Text('Soumettre'),
          ),
        ],
      ),
    );
  }

  Widget _buildLoading() => const Scaffold(
    backgroundColor: Color(0xFF0D0B1F),
    body: Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircularProgressIndicator(color: Color(0xFF5C35D9)),
          SizedBox(height: 20),
          Text('Génération du quiz par l\'IA...', style: TextStyle(color: Colors.white70)),
          SizedBox(height: 8),
          Text('Patience, ça peut prendre 10-20 secondes 😊',
            style: TextStyle(color: Color(0xFF6B6490), fontSize: 13)),
        ],
      ),
    ),
  );

  Widget _buildError() => Scaffold(
    backgroundColor: const Color(0xFF0D0B1F),
    body: Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Text('❌', style: TextStyle(fontSize: 50)),
            const SizedBox(height: 16),
            Text(_error!, style: const TextStyle(color: Colors.white, fontSize: 16), textAlign: TextAlign.center),
            const SizedBox(height: 24),
            ElevatedButton(onPressed: _loadQuiz, child: const Text('Réessayer')),
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Retour'),
            ),
          ],
        ),
      ),
    ),
  );
}

class _DifficultyBadge extends StatelessWidget {
  final String difficulty;
  const _DifficultyBadge(this.difficulty);

  @override
  Widget build(BuildContext context) {
    final color = switch (difficulty) {
      'facile'   => Colors.green,
      'difficile'=> Colors.red,
      _          => Colors.orange,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.15),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withOpacity(0.5)),
      ),
      child: Text(difficulty, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }
}
