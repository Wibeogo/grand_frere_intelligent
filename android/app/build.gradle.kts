plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "bf.tiragepromobf.grand_frere_intelligent"
    compileSdk = 36
    ndkVersion = flutter.ndkVersion

    compileOptions {
        // ✅ REQUIS par flutter_local_notifications >= 9.0 et timezone
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_11.toString()
    }

    defaultConfig {
        // ID unique de l'application – à personnaliser
        applicationId = "bf.tiragepromobf.grand_frere_intelligent"
        // minSdk 21 requis par plusieurs plugins (firebase_messaging, permission_handler, etc.)
        minSdk = flutter.minSdkVersion
        targetSdk = 36
        versionCode = flutter.versionCode
        versionName = flutter.versionName
        multiDexEnabled = true
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("debug")
            isMinifyEnabled = false
            isShrinkResources = false
        }
        debug {
            isDebuggable = true
        }
    }
}

flutter {
    source = "../.."
}

dependencies {
    // ✅ Requis pour le core library desugaring (Java 8+ APIs sur Android < 26)
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}
