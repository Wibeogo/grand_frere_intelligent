/// Modèles pour les quiz
class QuizModel {
  final String quizTitle;
  final String category;
  final String matiere;
  final List<QuizQuestion> questions;
  final String quizDataEncoded;
  final String quizToken;

  const QuizModel({
    required this.quizTitle,
    required this.category,
    required this.matiere,
    required this.questions,
    required this.quizDataEncoded,
    required this.quizToken,
  });

  int get nbQuestions => questions.length;

  factory QuizModel.fromJson(Map<String, dynamic> json) {
    return QuizModel(
      quizTitle:       json['quiz_title']        as String? ?? 'Quiz',
      category:        json['category']           as String? ?? '',
      matiere:         json['matiere']            as String? ?? '',
      questions:       ((json['questions'] as List?) ?? [])
          .map((q) => QuizQuestion.fromJson(q as Map<String, dynamic>))
          .toList(),
      quizDataEncoded: json['quiz_data_encoded']  as String? ?? '',
      quizToken:       json['quiz_token']         as String? ?? '',
    );
  }
}

class QuizQuestion {
  final int id;
  final String question;
  final Map<String, String> options;
  final String difficulty;
  // Champs disponibles après soumission
  final String? correctAnswer;
  final String? explanation;
  final String? userAnswer;
  final bool? isCorrect;

  const QuizQuestion({
    required this.id,
    required this.question,
    required this.options,
    required this.difficulty,
    this.correctAnswer,
    this.explanation,
    this.userAnswer,
    this.isCorrect,
  });

  factory QuizQuestion.fromJson(Map<String, dynamic> json) {
    return QuizQuestion(
      id:            json['id'] as int,
      question:      json['question'] as String,
      options:       Map<String, String>.from(json['options'] as Map),
      difficulty:    json['difficulty'] as String? ?? 'moyen',
      correctAnswer: json['correct_answer'] as String?,
      explanation:   json['explanation'] as String?,
      userAnswer:    json['user_answer'] as String?,
      isCorrect:     json['is_correct'] as bool?,
    );
  }

  QuizQuestion copyWith({bool? isCorrect, String? userAnswer}) {
    return QuizQuestion(
      id:            id,
      question:      question,
      options:       options,
      difficulty:    difficulty,
      correctAnswer: correctAnswer,
      explanation:   explanation,
      userAnswer:    userAnswer ?? this.userAnswer,
      isCorrect:     isCorrect  ?? this.isCorrect,
    );
  }
}

/// Résultat d'un quiz soumis
class QuizResult {
  final int score;
  final int total;
  final int percentage;
  final String appreciation;
  final List<QuizQuestion> details;
  final String category;

  const QuizResult({
    required this.score,
    required this.total,
    required this.percentage,
    required this.appreciation,
    required this.details,
    required this.category,
  });

  factory QuizResult.fromJson(Map<String, dynamic> json) {
    return QuizResult(
      score:        json['score']       as int,
      total:        json['total']       as int,
      percentage:   json['percentage']  as int,
      appreciation: json['appreciation'] as String,
      category:     json['category']    as String? ?? '',
      details:      ((json['details'] as List?) ?? [])
          .map((d) => QuizQuestion.fromJson(d as Map<String, dynamic>))
          .toList(),
    );
  }
}

/// Score de quiz sauvegardé
class QuizScore {
  final int id;
  final int score;
  final int total;
  final String category;
  final DateTime createdAt;

  const QuizScore({
    required this.id,
    required this.score,
    required this.total,
    required this.category,
    required this.createdAt,
  });

  double get percentage => total > 0 ? (score / total) * 100 : 0;

  factory QuizScore.fromJson(Map<String, dynamic> json) {
    return QuizScore(
      id:        json['id']       as int,
      score:     json['score']    as int,
      total:     json['total']    as int,
      category:  json['category'] as String,
      createdAt: DateTime.tryParse(json['created_at'] as String? ?? '') ?? DateTime.now(),
    );
  }
}
