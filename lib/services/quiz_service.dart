import '../config/api_config.dart';
import '../models/quiz_model.dart';
import '../models/exam_model.dart';
import 'api_service.dart';
import 'chat_service.dart';

/// Service quiz et examens blancs
class QuizService {
  // ─── Quiz ─────────────────────────────────────────────────────

  static Future<QuizModel> generateQuiz({
    String? category,
    String? matiere,
    int nbQuestions = 10,
  }) async {
    final body = <String, dynamic>{
      'nb_questions': nbQuestions,
      if (category != null) 'category': category,
      if (matiere  != null) 'matiere':  matiere,
    };

    final response = await ApiService.post(ApiConfig.quizGenerate, body);
    if (response.success && response.data != null) {
      return QuizModel.fromJson(response.data!);
    }
    if (response.isSubscriptionError) throw SubscriptionRequiredException();
    throw ApiException(response.errorMessage ?? 'Impossible de générer le quiz.');
  }

  static Future<QuizResult> submitQuiz({
    required String quizDataEncoded,
    required String quizToken,
    required Map<String, String> answers,
    String? category,
  }) async {
    final response = await ApiService.post(ApiConfig.quizSubmit, {
      'quiz_data_encoded': quizDataEncoded,
      'quiz_token':        quizToken,
      'answers':           answers,
      if (category != null) 'category': category,
    });

    if (response.success && response.data != null) {
      return QuizResult.fromJson(response.data!);
    }
    throw ApiException(response.errorMessage ?? 'Erreur lors de la correction.');
  }

  // ─── Examens blancs ───────────────────────────────────────────

  static Future<ExamModel> generateExam({
    required String concours,
    int nbQcm          = 20,
    int nbOuvertes     = 2,
    int durationMinutes= 120,
  }) async {
    final response = await ApiService.post(ApiConfig.examGenerate, {
      'concours':        concours,
      'nb_qcm':          nbQcm,
      'nb_ouvertes':     nbOuvertes,
      'duree_minutes':   durationMinutes,
    });

    if (response.success && response.data != null) {
      return ExamModel.fromJson(response.data!);
    }
    if (response.isSubscriptionError) throw SubscriptionRequiredException();
    throw ApiException(response.errorMessage ?? 'Impossible de générer l\'examen.');
  }

  static Future<ExamResult> submitExam({
    required String examEncoded,
    required String examToken,
    required Map<String, String> answers,
    int durationTaken = 0,
  }) async {
    final response = await ApiService.post(ApiConfig.examSubmit, {
      'exam_encoded':   examEncoded,
      'exam_token':     examToken,
      'answers':        answers,
      'duration_taken': durationTaken,
    });

    if (response.success && response.data != null) {
      return ExamResult.fromJson(response.data!);
    }
    throw ApiException(response.errorMessage ?? 'Erreur lors de la correction de l\'examen.');
  }
}
