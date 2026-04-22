import 'dart:convert';

/// Modèle utilisateur et statut de son abonnement
class UserModel {
  final int id;
  final String email;
  final String? fullName;
  final String phone;
  final String userType;
  final bool isPremium;
  final String subscriptionStatus; // 'trial', 'premium', 'expired'
  final int trialDaysLeft;
  final int subscriptionDaysLeft;
  final String? trialEndsAt;
  final String? subscriptionExpiry;
  final String? concoursCible;
  final UserStats? stats;

  const UserModel({
    required this.id,
    required this.email,
    this.fullName,
    required this.phone,
    required this.userType,
    required this.isPremium,
    required this.subscriptionStatus,
    required this.trialDaysLeft,
    required this.subscriptionDaysLeft,
    this.trialEndsAt,
    this.subscriptionExpiry,
    this.concoursCible,
    this.stats,
  });

  String get displayName => fullName?.isNotEmpty == true ? fullName! : email;
  String get firstName => displayName.split(' ').first;

  bool get isInTrial   => subscriptionStatus == 'trial';
  bool get hasActiveSub=> subscriptionStatus == 'premium';
  bool get isExpired   => subscriptionStatus == 'expired';

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id:                   json['id'] as int,
      email:                json['email'] as String,
      fullName:             json['full_name'] as String?,
      phone:                json['phone'] as String? ?? '',
      userType:             json['user_type'] as String? ?? 'free',
      isPremium:            json['is_premium'] as bool? ?? false,
      subscriptionStatus:   json['subscription_status'] as String? ?? 'expired',
      trialDaysLeft:        json['trial_days_left'] as int? ?? 0,
      subscriptionDaysLeft: json['subscription_days_left'] as int? ?? 0,
      trialEndsAt:          json['trial_ends_at'] as String?,
      subscriptionExpiry:   json['subscription_expiry'] as String?,
      concoursCible:        json['concours_cible'] as String?,
      stats: json['stats'] != null
          ? UserStats.fromJson(json['stats'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'email': email,
    'full_name': fullName,
    'phone': phone,
    'user_type': userType,
    'is_premium': isPremium,
    'subscription_status': subscriptionStatus,
    'trial_days_left': trialDaysLeft,
    'subscription_days_left': subscriptionDaysLeft,
    'trial_ends_at': trialEndsAt,
    'subscription_expiry': subscriptionExpiry,
    'concours_cible': concoursCible,
  };

  String toJsonString() => jsonEncode(toJson());

  static UserModel? fromJsonString(String jsonStr) {
    try {
      return UserModel.fromJson(jsonDecode(jsonStr) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  UserModel copyWith({
    String? concoursCible,
    bool? isPremium,
    String? subscriptionStatus,
    UserStats? stats,
  }) {
    return UserModel(
      id: id,
      email: email,
      fullName: fullName,
      phone: phone,
      userType: userType,
      isPremium: isPremium ?? this.isPremium,
      subscriptionStatus: subscriptionStatus ?? this.subscriptionStatus,
      trialDaysLeft: trialDaysLeft,
      subscriptionDaysLeft: subscriptionDaysLeft,
      trialEndsAt: trialEndsAt,
      subscriptionExpiry: subscriptionExpiry,
      concoursCible: concoursCible ?? this.concoursCible,
      stats: stats ?? this.stats,
    );
  }
}

/// Statistiques de l'utilisateur
class UserStats {
  final int totalQuiz;
  final double avgScore;
  final int totalExams;
  final int totalMessages;

  const UserStats({
    required this.totalQuiz,
    required this.avgScore,
    required this.totalExams,
    required this.totalMessages,
  });

  factory UserStats.fromJson(Map<String, dynamic> json) {
    return UserStats(
      totalQuiz:     (json['total_quiz']     as num?)?.toInt()    ?? 0,
      avgScore:      (json['avg_score']      as num?)?.toDouble() ?? 0.0,
      totalExams:    (json['total_exams']    as num?)?.toInt()    ?? 0,
      totalMessages: (json['total_messages'] as num?)?.toInt()    ?? 0,
    );
  }
}

/// Modèle d'un concours burkinabè
class ConcoursModel {
  final String key;
  final String nom;
  final String emoji;
  final String description;
  final String durationPrep;

  const ConcoursModel({
    required this.key,
    required this.nom,
    required this.emoji,
    required this.description,
    required this.durationPrep,
  });

  /// Liste statique des concours disponibles
  static List<ConcoursModel> get allConcours => [
    const ConcoursModel(key: 'police',                 nom: 'Police Nationale',        emoji: '👮', description: 'ENAPOSC – École Nationale de Police',               durationPrep: '6 mois'),
    const ConcoursModel(key: 'enaref',                 nom: 'ENAREF',                  emoji: '🏦', description: 'Fiscalité, Douanes & Régies Financières',           durationPrep: '8 mois'),
    const ConcoursModel(key: 'enseignement_primaire',  nom: 'Enseignement Primaire',   emoji: '📚', description: 'ENEP – Instituteur primaire',                       durationPrep: '4 mois'),
    const ConcoursModel(key: 'enseignement_secondaire',nom: 'Enseignement Secondaire', emoji: '🎓', description: 'ENS – Professeur du secondaire',                    durationPrep: '6 mois'),
    const ConcoursModel(key: 'sante',                  nom: 'Santé Publique',          emoji: '🏥', description: 'Infirmier, Sage-femme, Technicien de santé',         durationPrep: '6 mois'),
    const ConcoursModel(key: 'douanes',                nom: 'Douanes Nationales',      emoji: '🚛', description: 'Direction Générale des Douanes',                     durationPrep: '6 mois'),
    const ConcoursModel(key: 'eaux_forets',            nom: 'Eaux & Forêts',           emoji: '🌿', description: 'Agent de l\'environnement et des forêts',           durationPrep: '4 mois'),
    const ConcoursModel(key: 'magistrature',           nom: 'Magistrature',            emoji: '⚖️', description: 'ENAM – Magistrat, Conseiller juridique',             durationPrep: '12 mois'),
    const ConcoursModel(key: 'gendarmerie',            nom: 'Gendarmerie Nationale',   emoji: '🎖️', description: 'École Nationale de Gendarmerie',                    durationPrep: '5 mois'),
    const ConcoursModel(key: 'administration',         nom: 'Administration',          emoji: '🏛️', description: 'ENAM – Administration générale & Affaires étrangères', durationPrep: '8 mois'),
  ];
}
