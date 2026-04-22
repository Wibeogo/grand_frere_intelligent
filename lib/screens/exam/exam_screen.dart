import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../models/exam_model.dart';
import '../../providers/auth_provider.dart';
import '../../services/quiz_service.dart';
import '../../services/chat_service.dart';

/// Écran examen blanc avec timer compte à rebours
class ExamScreen extends StatefulWidget {
  const ExamScreen({super.key});
  @override
  State<ExamScreen> createState() => _ExamScreenState();
}

class _ExamScreenState extends State<ExamScreen> {
  ExamModel? _exam;
  bool   _loading   = true;
  String? _error;
  final Map<String, String> _answers = {};
  Timer?  _timer;
  int     _secondsLeft = 0;
  bool    _examStarted = false;
  DateTime? _startTime;
  int     _currentSection = 0;

  static const _primary = Color(0xFF5C35D9);
  static const _gold    = Color(0xFFF0B429);

  @override
  void initState() {
    super.initState();
    _loadExam();
  }

  Future<void> _loadExam() async {
    final auth = context.read<AuthProvider>();
    setState(() { _loading = true; _error = null; });

    try {
      final exam = await QuizService.generateExam(
        concours: auth.user?.concoursCible ?? 'general',
      );
      setState(() {
        _exam         = exam;
        _loading      = false;
        _secondsLeft  = exam.durationMinutes * 60;
      });
    } on SubscriptionRequiredException {
      setState(() { _error = 'Abonnement premium requis.'; _loading = false; });
    } on ApiException catch (e) {
      setState(() { _error = e.message; _loading = false; });
    }
  }

  void _startExam() {
    setState(() { _examStarted = true; _startTime = DateTime.now(); });
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (_secondsLeft <= 0) {
        _timer?.cancel();
        _submitExam();
      } else {
        setState(() => _secondsLeft--);
      }
    });
  }

  Future<void> _submitExam() async {
    _timer?.cancel();
    if (_exam == null) return;

    final durationTaken = _startTime != null
        ? DateTime.now().difference(_startTime!).inMinutes
        : 0;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(color: Color(0xFF5C35D9)),
            SizedBox(height: 16),
            Text('Correction en cours par l\'IA...', style: TextStyle(color: Colors.white)),
          ],
        ),
      ),
    );

    try {
      final result = await QuizService.submitExam(
        examEncoded:   _exam!.examEncoded,
        examToken:     _exam!.examToken,
        answers:       _answers,
        durationTaken: durationTaken,
      );

      if (!mounted) return;
      Navigator.pop(context);
      Navigator.pushReplacementNamed(context, '/exam-result', arguments: result);
    } catch (e) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Erreur : $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  String _formatTime(int seconds) {
    final h = seconds ~/ 3600;
    final m = (seconds % 3600) ~/ 60;
    final s = seconds % 60;
    if (h > 0) return '${h}h ${m.toString().padLeft(2,'0')}m';
    return '${m.toString().padLeft(2,'0')}:${s.toString().padLeft(2,'0')}';
  }

  @override
  Widget build(BuildContext context) {
    if (_loading)  return _buildLoading();
    if (_error != null) return _buildError();
    if (!_examStarted) return _buildStartPage();
    return _buildExamPage();
  }

  Widget _buildStartPage() {
    return Scaffold(
      backgroundColor: const Color(0xFF0D0B1F),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              IconButton(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close, color: Colors.white),
                alignment: Alignment.centerLeft,
              ),
              const Spacer(),
              const Text('📋', style: TextStyle(fontSize: 60)),
              const SizedBox(height: 20),
              Text(
                _exam!.examTitle,
                style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 20),
              _infoCard('⏱️ Durée',    '${_exam!.durationMinutes} minutes'),
              _infoCard('❓ Questions', '${_exam!.totalQuestions} questions'),
              _infoCard('🏆 Points',   '${_exam!.totalPoints} points'),
              const SizedBox(height: 20),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color:        const Color(0xFF231E42),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  _exam!.instructions,
                  style: const TextStyle(color: Color(0xFFBBB5D8), fontSize: 13, height: 1.5),
                ),
              ),
              const Spacer(),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _startExam,
                  style: ElevatedButton.styleFrom(backgroundColor: _gold),
                  child: const Text('🚀 Démarrer l\'examen', style: TextStyle(fontSize: 16)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildExamPage() {
    final sections = _exam!.sections;
    final section  = sections[_currentSection];
    final timeColor= _secondsLeft < 300 ? Colors.red : Colors.white;

    return Scaffold(
      backgroundColor: const Color(0xFF0D0B1F),
      appBar: AppBar(
        title: Text(section.sectionTitle, style: const TextStyle(fontSize: 14)),
        backgroundColor: const Color(0xFF0D0B1F),
        actions: [
          // Timer
          Container(
            margin: const EdgeInsets.only(right: 12),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color:        _secondsLeft < 300 ? Colors.red.withOpacity(0.2) : const Color(0xFF231E42),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: _secondsLeft < 300 ? Colors.red : const Color(0xFF3A3060),
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.timer, color: timeColor, size: 14),
                const SizedBox(width: 4),
                Text(_formatTime(_secondsLeft), style: TextStyle(color: timeColor, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          // Onglets sections
          if (sections.length > 1)
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: sections.asMap().entries.map((e) {
                  final isActive = _currentSection == e.key;
                  return GestureDetector(
                    onTap: () => setState(() => _currentSection = e.key),
                    child: Container(
                      margin:  const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      decoration: BoxDecoration(
                        color:  isActive ? _primary : const Color(0xFF231E42),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        'Partie ${e.key + 1}',
                        style: TextStyle(
                          color: isActive ? Colors.white : const Color(0xFF9D96C0),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),

          // Questions
          Expanded(
            child: ListView.builder(
              padding:   const EdgeInsets.all(16),
              itemCount: section.questions.length,
              itemBuilder: (ctx, i) {
                final q = section.questions[i];
                return _buildQuestion(q, i + 1);
              },
            ),
          ),

          // Bouton soumettre
          Padding(
            padding: const EdgeInsets.all(16),
            child: SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => _showSubmitConfirm(),
                style: ElevatedButton.styleFrom(backgroundColor: _gold),
                child: Text('Soumettre l\'examen (${_answers.length}/${_exam!.totalQuestions} réponses)'),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildQuestion(ExamQuestion q, int num) {
    final answered = _answers[q.id.toString()];

    return Container(
      margin:  const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color:        const Color(0xFF1A1730),
        borderRadius: BorderRadius.circular(16),
        border:       Border.all(color: const Color(0xFF3A3060)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: _primary.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text('Q$num – ${q.points} pt(s)',
                  style: const TextStyle(color: Color(0xFF9D96C0), fontSize: 11)),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(q.question, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, height: 1.5)),
          const SizedBox(height: 12),

          if (q.isQcm && q.options != null)
            ...q.options!.entries.map((e) {
              final isSelected = answered == e.key;
              return GestureDetector(
                onTap: () => setState(() => _answers[q.id.toString()] = e.key),
                child: Container(
                  margin:  const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color:        isSelected ? _primary.withOpacity(0.2) : const Color(0xFF231E42),
                    borderRadius: BorderRadius.circular(10),
                    border:       Border.all(color: isSelected ? _primary : const Color(0xFF3A3060)),
                  ),
                  child: Row(
                    children: [
                      Text('${e.key}. ', style: const TextStyle(color: Color(0xFF9D96C0))),
                      Expanded(child: Text(e.value, style: TextStyle(
                        color: isSelected ? Colors.white : const Color(0xFFBBB5D8),
                      ))),
                    ],
                  ),
                ),
              );
            })
          else
            TextField(
              maxLines:          8,
              minLines:          4,
              style:             const TextStyle(color: Colors.white, fontSize: 14),
              onChanged:         (v) => _answers[q.id.toString()] = v,
              decoration:        const InputDecoration(
                hintText:        'Écris ta réponse ici...',
                hintStyle:       TextStyle(color: Color(0xFF6B6490)),
                filled:          true,
                fillColor:       Color(0xFF231E42),
                border:          OutlineInputBorder(
                  borderRadius:  BorderRadius.all(Radius.circular(10)),
                  borderSide:    BorderSide(color: Color(0xFF3A3060)),
                ),
              ),
            ),
        ],
      ),
    );
  }

  void _showSubmitConfirm() {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: const Color(0xFF231E42),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Soumettre l\'examen ?', style: TextStyle(color: Colors.white)),
        content: Text(
          'Tu as répondu à ${_answers.length}/${_exam!.totalQuestions} questions.\n'
          'L\'IA va corriger tes réponses. Cette action est irréversible.',
          style: const TextStyle(color: Color(0xFF9D96C0)),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Continuer')),
          ElevatedButton(
            onPressed: () { Navigator.pop(context); _submitExam(); },
            style: ElevatedButton.styleFrom(backgroundColor: _gold),
            child: const Text('Soumettre'),
          ),
        ],
      ),
    );
  }

  Widget _infoCard(String label, String value) {
    return Container(
      margin:  const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color:        const Color(0xFF231E42),
        borderRadius: BorderRadius.circular(12),
        border:       Border.all(color: const Color(0xFF3A3060)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Color(0xFF9D96C0))),
          Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
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
          Text('Génération de l\'examen blanc...', style: TextStyle(color: Colors.white70)),
          SizedBox(height: 8),
          Text('L\'IA prépare un examen complet pour toi 📋',
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
            ElevatedButton(onPressed: _loadExam, child: const Text('Réessayer')),
          ],
        ),
      ),
    ),
  );
}
