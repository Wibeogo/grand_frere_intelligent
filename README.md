# Grand Frère Intelligent 🎓
## Application mobile de préparation aux concours burkinabè

> Application Flutter + PHP API + IA Ollama (Mistral 7B / LLaVA 7B)

---

## 📁 Structure du projet

```
grand_frere_intelligent/
├── backend/              # API PHP (déployer sur Hostinger)
│   ├── api/              # Tous les endpoints REST
│   ├── includes/         # Helpers partagés
│   ├── logs/             # Journaux d'erreurs
│   ├── composer.json     # Dépendances PHP
│   ├── .env.example      # Variables d'environnement (à copier en .env)
│   └── install.php       # Script de création des tables MySQL
│
├── colab/
│   └── colab_grand_frere.ipynb  # Notebook Google Colab (IA)
│
├── lib/                  # Code Flutter
│   ├── config/           # Configuration API
│   ├── models/           # Modèles de données
│   ├── services/         # Services API
│   ├── providers/        # État de l'application (Provider)
│   ├── screens/          # Écrans Flutter
│   └── widgets/          # Widgets réutilisables
│
├── pubspec.yaml          # Dépendances Flutter
└── README.md
```

---

## 🚀 Déploiement – Etape par étape

### ÉTAPE 1 – Backend PHP sur Hostinger

#### 1.1 Prérequis Hostinger
- Hébergement web Hostinger (Plan Business ou Premium)
- Base de données MySQL créée depuis le panel Hostinger
- PHP 8.x activé

#### 1.2 Upload des fichiers
```bash
# Via FTP (FileZilla, WinSCP) ou Gestionnaire de fichiers Hostinger
# Uploader tout le dossier backend/ dans :
# /home/user/public_html/
```

#### 1.3 Installer les dépendances Composer
```bash
# Via SSH Hostinger (ou terminal Hostinger)
cd /home/user/public_html
composer install --no-dev --optimize-autoloader
```

#### 1.4 Configurer le fichier .env
```bash
cp .env.example .env
nano .env  # ou éditer via le gestionnaire de fichiers
```
- Remplir **DB_HOST, DB_NAME, DB_USER, DB_PASS** avec les identifiants MySQL Hostinger
- Générer une **JWT_SECRET** aléatoire : `openssl rand -base64 64`
- Laisser **IA_API_URL** vide pour l'instant (à remplir après le Colab)

#### 1.5 Créer les tables MySQL
```
Accéder à : https://votredomaine.com/install.php?secret=VOTRE_JWT_SECRET
```
> **⚠️ IMPORTANT : Supprimer install.php immédiatement après l'installation !**

#### 1.6 Configurer les Cron Jobs Hostinger
Dans le panel Hostinger → **Cron Jobs (Avancé)** :

| Fréquence | Commande |
|-----------|----------|
| `0 8 * * *` (8h chaque jour) | `/usr/bin/php /home/user/public_html/api/daily_quiz_cron.php` |
| `0 1 * * *` (1h chaque nuit) | `/usr/bin/php /home/user/public_html/api/check_trial_expiry.php` |

---

### ÉTAPE 2 – Intelligence Artificielle sur Google Colab

#### 2.1 Ouvrir le notebook
1. Aller sur [colab.research.google.com](https://colab.research.google.com)
2. Ouvrir le fichier `colab/colab_grand_frere.ipynb`
3. **Activer le GPU T4** : Runtime → Change runtime type → T4 GPU

#### 2.2 Exécuter les cellules dans l'ordre
| Cellule | Action |
|---------|--------|
| 1 | Installation d'Ollama |
| 2 | Démarrage du serveur |
| 3 | Téléchargement des modèles (~10-20 min) |
| 4 | Création du modèle personnalisé "Grand Frère" |
| 5 | Test du modèle |
| 6 | **Cloudflare Tunnel → copier l'URL publique** |
| 7 | Keep-alive (laisser tourner) |
| 8 | Test de connectivité |

#### 2.3 Mettre à jour le backend
```bash
# Dans /home/user/public_html/.env
IA_API_URL=https://VOTRE-URL-COLAB.trycloudflare.com
```

> **⚠️ L'URL change à chaque session Colab ! Mettre à jour le .env à chaque redémarrage.**

---

### ÉTAPE 3 – Orange SMS (Burkina Faso)

#### 3.1 Créer un compte développeur Orange
1. Aller sur [developer.orange.com](https://developer.orange.com)
2. Créer un compte et se connecter
3. Créer une nouvelle application
4. Choisir l'API "SMS Messaging BF" (Burkina Faso)
5. Obtenir le **Client ID** et **Client Secret**

#### 3.2 Configurer le .env
```env
ORANGE_SMS_CLIENT_ID=votre_client_id
ORANGE_SMS_CLIENT_SECRET=votre_client_secret
ORANGE_SMS_SENDER=tel:+22601000000  # Votre numéro expéditeur
```

#### 3.3 Acheter des crédits SMS
- Se connecter au portail Orange Developer
- Section "Billing" → acheter des lots de SMS
- Les SMS sont facturés par unité

---

### ÉTAPE 4 – Senfenico (Paiement)

#### 4.1 Créer un compte Senfenico
1. Aller sur [senfenico.com](https://senfenico.com)
2. Créer un compte marchand
3. Passer en mode live (vérification d'identité requise)
4. Obtenir la **clé secrète** et le **webhook secret**

#### 4.2 Configurer les URLs dans Senfenico
- **Webhook URL** : `https://votredomaine.com/api/payment_webhook.php`
- **Success URL** : `https://votredomaine.com/payment_success.html`
- **Cancel URL** : `https://votredomaine.com/payment_cancel.html`

#### 4.3 Configurer le .env
```env
SENFENICO_SECRET_KEY=sk_live_...
SENFENICO_WEBHOOK_SECRET=whsec_...
```

---

### ÉTAPE 5 – Firebase (Notifications Push)

#### 5.1 Créer un projet Firebase
1. Aller sur [console.firebase.google.com](https://console.firebase.google.com)
2. Créer un nouveau projet "grand-frere-intelligent"
3. Activer **Cloud Messaging (FCM)**

#### 5.2 Android
1. Ajouter une application Android (package : `com.grandfrere.intelligent`)
2. Télécharger `google-services.json`
3. Placer dans `android/app/google-services.json`

#### 5.3 iOS (optionnel)
1. Ajouter une application iOS
2. Télécharger `GoogleService-Info.plist`
3. Placer dans `ios/Runner/GoogleService-Info.plist`

#### 5.4 Clé serveur FCM
1. Firebase Console → Paramètres du projet → Cloud Messaging
2. Copier la **clé serveur** dans `.env` : `FCM_SERVER_KEY=...`

---

### ÉTAPE 6 – Application Flutter

#### 6.1 Prérequis
```bash
# Vérifier l'installation Flutter
flutter doctor
```

#### 6.2 Installation des dépendances
```bash
cd c:\flutter_projects\grand_frere_intelligent
flutter pub get
```

#### 6.3 Configurer l'URL de l'API
```dart
// lib/config/api_config.dart
static const String baseUrl = 'https://votredomaine.com/api';
```

#### 6.4 Lancer en développement
```bash
flutter run
```

#### 6.5 Générer l'APK Android
```bash
flutter build apk --release
# APK dans : build/app/outputs/flutter-apk/app-release.apk
```

#### 6.6 Générer l'AAB (Google Play)
```bash
flutter build appbundle --release
```

---

## 🧪 Test des fonctionnalités

### Test du Chat
```bash
curl -X POST https://votredomaine.com/api/register.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"123456","phone":"70000000","full_name":"Test User"}'
```

### Test du Chat (avec token)
```bash
TOKEN="votre_token_jwt"
curl -X POST https://votredomaine.com/api/chat_send.php \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message":"Explique-moi la constitution burkinabè","concours":"magistrature"}'
```

### Test SMS
```bash
curl -X POST https://votredomaine.com/api/send_sms.php \
  -H "Authorization: Bearer $TOKEN" \
  -d '_direct_api_call=1&phone=70000000&message=Test SMS Grand Frere'
```

---

## 📋 Checklist de déploiement

- [ ] Backend PHP uploadé sur Hostinger
- [ ] `composer install` exécuté
- [ ] Fichier `.env` configuré
- [ ] `install.php` exécuté (tables créées) et **supprimé**
- [ ] Cron jobs configurés
- [ ] Notebook Colab lancé et URL copiée dans `.env`
- [ ] Orange Developer : Client ID/Secret configurés
- [ ] Senfenico : clés API et webhook configurés
- [ ] Firebase : `google-services.json` placé dans Android
- [ ] `api_config.dart` mis à jour avec l'URL du backend
- [ ] `flutter pub get` exécuté
- [ ] APK généré et testé sur appareil réel

---

## ⚠️ Sécurité – Points importants

1. **Ne jamais committer le fichier `.env`** – il contient des clés secrètes
2. **Supprimer `install.php`** immédiatement après l'installation
3. **HTTPS obligatoire** – configurer un certificat SSL sur Hostinger (gratuit avec Let's Encrypt)
4. **Sauvegarder la BDD** régulièrement via le panel Hostinger

---

## 💰 Modèle économique

| Composant | Coût |
|-----------|------|
| Hostinger (hébergement PHP) | ~3-8 €/mois |
| Google Colab Pro (GPU T4) | ~11 $/mois (optionnel) |
| Orange SMS | ~50-80 FCFA/SMS selon forfait |
| Senfenico | Commission sur transactions (voir tarifs) |
| Firebase (FCM) | Gratuit jusqu'à 20k messages/mois |
| **Revenu abonnement** | **2800 FCFA/mois/utilisateur** |

---

## 📞 Support

Pour toute question sur le déploiement, référez-vous à :
- Documentation Hostinger : [support.hostinger.com](https://support.hostinger.com)
- Documentation Orange Developer : [developer.orange.com/apis/sms-bf](https://developer.orange.com/apis/sms-bf)
- Documentation Senfenico : [docs.senfenico.com](https://docs.senfenico.com)
- Documentation Flutter : [docs.flutter.dev](https://docs.flutter.dev)

---

*Développé avec ❤️ pour les candidats aux concours burkinabè 🇧🇫*
