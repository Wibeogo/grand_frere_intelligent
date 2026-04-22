import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart' show Color;
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'auth_service.dart';

/// Service de notifications push (Firebase Cloud Messaging)
class NotificationService {
  static final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();
  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;

  /// Initialiser le service de notifications
  static Future<void> initialize() async {
    // Demander les permissions
    final settings = await _messaging.requestPermission(
      alert:       true,
      badge:       true,
      sound:       true,
      provisional: false,
    );

    if (settings.authorizationStatus == AuthorizationStatus.denied) {
      return;
    }

    // Configuration des notifications locales
    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings     = DarwinInitializationSettings(
      requestAlertPermission:  true,
      requestBadgePermission:  true,
      requestSoundPermission:  true,
    );

    await _localNotifications.initialize(
      const InitializationSettings(android: androidSettings, iOS: iosSettings),
    );

    // Canal de notification Android
    const channel = AndroidNotificationChannel(
      'grand_frere_channel',
      'Grand Frère Intelligent',
      description: 'Notifications de l\'application Grand Frère',
      importance:  Importance.high,
    );
    await _localNotifications
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    // Gérer les messages en foreground
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // Enregistrer le token FCM sur le serveur
    await registerFcmToken();
  }

  /// Enregistrer le token FCM sur le serveur
  static Future<void> registerFcmToken() async {
    try {
      final token = await _messaging.getToken();
      if (token != null) {
        await AuthService.updateProfile(fcmToken: token);
      }

      // Mettre à jour si le token change
      _messaging.onTokenRefresh.listen((newToken) {
        AuthService.updateProfile(fcmToken: newToken);
      });
    } catch (e) {
      // Ignorer si non connecté
    }
  }

  /// Afficher une notification locale depuis un message FCM en foreground
  static Future<void> _handleForegroundMessage(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    await _localNotifications.show(
      notification.hashCode,
      notification.title,
      notification.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          'grand_frere_channel',
          'Grand Frère Intelligent',
          channelDescription: 'Notifications Grand Frère',
          importance:  Importance.high,
          priority:    Priority.high,
          color:       const Color(0xFF5C35D9),
        ),
        iOS: const DarwinNotificationDetails(),
      ),
    );
  }
}
