/// Modèles pour les examens blancs
class ExamModel {
  final String examTitle;
  final String concours;
  final int durationMinutes;
  final int totalPoints;
  final String instructions;
  final List<ExamSection> sections;
  final String examEncoded;
  final String examToken;

  const ExamModel({
    required this.examTitle,
    required this.concours,
    required this.durationMinutes,
    required this.totalPoints,
    required this.instructions,
    required this.sections,
    required this.examEncoded,
    required this.examToken,
  });

  int get totalQuestions => sections.fold(0, (sum, s) => sum + s.questions.length);

  factory ExamModel.fromJson(Map<String, dynamic> json) {
    final exam = json['exam'] as Map<String, dynamic>? ?? json;
    return ExamModel(
      examTitle:       exam['exam_title']        as String? ?? 'Examen Blanc',
      concours:        exam['concours']           as String? ?? '',
      durationMinutes: exam['duration_minutes']   as int?    ?? 120,
      totalPoints:     exam['total_points']       as int?    ?? 0,
      instructions:    exam['instructions']       as String? ?? '',
      sections:        ((exam['sections'] as List?) ?? [])
          .map((s) => ExamSection.fromJson(s as Map<String, dynamic>))
          .toList(),
      examEncoded:     json['exam_encoded']        as String? ?? '',
      examToken:       json['exam_token']          as String? ?? '',
    );
  }
}

class ExamSection {
  final int sectionId;
  final String sectionTitle;
  final String type; // 'qcm' ou 'ouverte'
  final List<ExamQuestion> questions;

  const ExamSection({
    required this.sectionId,
    required this.sectionTitle,
    required this.type,
    required this.questions,
  });

  factory ExamSection.fromJson(Map<String, dynamic> json) {
    return ExamSection(
      sectionId:    json['section_id']    as int,
      sectionTitle: json['section_title'] as String,
      type:         json['type']          as String,
      questions:    ((json['questions'] as List?) ?? [])
          .map((q) => ExamQuestion.fromJson(q as Map<String, dynamic>))
          .toList(),
    );
  }
}

class ExamQuestion {
  final int id;
  final String question;
  final Map<String, String>? options; // null pour questions ouvertes
  final String type;
  final int points;
  // Réponses (après soumission)
  final String? userAnswer;
  final String? correctAnswer;
  final bool? isCorrect;
  final int? pointsEarned;
  final String? explanation;
  final String? iaFeedback;

  const ExamQuestion({
    required this.id,
    required this.question,
    this.options,
    required this.type,
    required this.points,
    this.userAnswer,
    this.correctAnswer,
    this.isCorrect,
    this.pointsEarned,
    this.explanation,
    this.iaFeedback,
  });

  bool get isQcm     => type == 'qcm';
  bool get isOuverte => type == 'ouverte';

  factory ExamQuestion.fromJson(Map<String, dynamic> json) {
    return ExamQuestion(
      id:           json['id']            as int,
      question:     json['question']      as String,
      options:      json['options'] != null
          ? Map<String, String>.from(json['options'] as Map)
          : null,
      type:         json['type']          as String? ?? 'qcm',
      points:       json['points']        as int? ?? 1,
      userAnswer:   json['user_answer']   as String?,
      correctAnswer:json['correct_answer'] as String?,
      isCorrect:    json['is_correct']    as bool?,
      pointsEarned: json['points_earned'] as int?,
      explanation:  json['explanation']   as String?,
      iaFeedback:   json['ia_feedback']   as String?,
    );
  }

  ExamQuestion copyWith({String? userAnswer}) {
    return ExamQuestion(
      id:        id,
      question:  question,
      options:   options,
      type:      type,
      points:    points,
      userAnswer: userAnswer ?? this.userAnswer,
    );
  }
}

/// Résultat d'un examen blanc soumis
class ExamResult {
  final int score;
  final int totalPossible;
  final double percentage;
  final String appreciation;
  final int durationTaken;
  final List<ExamSectionResult> sections;

  const ExamResult({
    required this.score,
    required this.totalPossible,
    required this.percentage,
    required this.appreciation,
    required this.durationTaken,
    required this.sections,
  });

  factory ExamResult.fromJson(Map<String, dynamic> json) {
    return ExamResult(
      score:         json['score']          as int,
      totalPossible: json['total_possible'] as int,
      percentage:    (json['percentage']    as num).toDouble(),
      appreciation:  json['appreciation']   as String,
      durationTaken: json['duration_taken'] as int? ?? 0,
      sections:      ((json['sections'] as List?) ?? [])
          .map((s) => ExamSectionResult.fromJson(s as Map<String, dynamic>))
          .toList(),
    );
  }
}

class ExamSectionResult {
  final String sectionTitle;
  final String type;
  final List<ExamQuestion> questions;

  const ExamSectionResult({
    required this.sectionTitle,
    required this.type,
    required this.questions,
  });

  factory ExamSectionResult.fromJson(Map<String, dynamic> json) {
    return ExamSectionResult(
      sectionTitle: json['section_title'] as String,
      type:         json['type']          as String,
      questions:    ((json['questions'] as List?) ?? [])
          .map((q) => ExamQuestion.fromJson(q as Map<String, dynamic>))
          .toList(),
    );
  }
}
