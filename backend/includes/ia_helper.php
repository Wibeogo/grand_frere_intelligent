<?php
/**
 * includes/ia_helper.php – Interface avec l'API IA (Ollama sur Google Colab)
 * 
 * Fournit la fonction call_ia() qui envoie des requêtes au modèle Ollama
 * hébergé sur Google Colab via Cloudflare Tunnel.
 * 
 * Modèles utilisés :
 * - Mistral 7B : pour les messages texte et les quiz
 * - LLaVA 7B   : pour l'analyse d'images (correction de photos)
 */

require_once __DIR__ . '/db.php';

/**
 * Appelle l'API IA Ollama sur Colab.
 * 
 * @param string      $prompt        Prompt texte à envoyer
 * @param string|null $imageBase64   Image encodée en base64 (pour LLaVA)
 * @param string      $model         Modèle à utiliser (auto-détecté si image fournie)
 * @param bool        $returnJson    Si true, parse la réponse JSON de l'IA
 * @return string Réponse textuelle de l'IA
 */
function call_ia(string $prompt, ?string $imageBase64 = null, string $model = '', bool $returnJson = false): string {
    $apiUrl = rtrim($_ENV['IA_API_URL'] ?? '', '/');
    
    if (empty($apiUrl)) {
        logError('IA_API_URL non défini dans .env');
        return 'Service IA temporairement indisponible. Veuillez réessayer.';
    }
    
    // Sélection automatique du modèle
    if (empty($model)) {
        $model = ($imageBase64 !== null) ? 'llava:7b' : 'grandfrere:latest';
    }
    
    // Construction du corps de la requête pour /api/generate
    $body = [
        'model'  => $model,
        'prompt' => $prompt,
        'stream' => false,
    ];
    
    // LLaVA avec image
    if ($imageBase64 !== null) {
        $body['images'] = [$imageBase64];
    }
    
    $endpoint = '/api/generate';
    
    // Appel avec retry (2 tentatives)
    $maxRetries = 2;
    $lastError  = '';
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $result = httpPost($apiUrl . $endpoint, $body, 180);
        
        if ($result['success']) {
            $response = json_decode($result['body'], true);
            
            // Extraire le contenu de la réponse Ollama
            $content = $response['message']['content']
                    ?? $response['response']
                    ?? '';
            
            if (!empty($content)) {
                // Si on veut du JSON, essayer de l'extraire du texte
                if ($returnJson) {
                    return extractJsonFromText($content);
                }
                return trim($content);
            }
        }
        
        $lastError = $result['error'] ?? 'Réponse vide';
        
        if ($attempt < $maxRetries) {
            sleep(2); // Attendre 2s avant retry
        }
    }
    
    logError("Erreur IA après {$maxRetries} tentatives : {$lastError}");
    return 'Je rencontre une difficulté technique. Veuillez réessayer dans quelques instants.';
}

/**
 * Effectue une requête HTTP POST avec curl.
 */
function httpPost(string $url, array $data, int $timeout = 180): array {
    // Permet au script PHP de s'exécuter plus longtemps (Hostinger coupe souvent à 60s)
    set_time_limit($timeout);
    
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 30, // 30s pour se connecter à Cloudflare
        CURLOPT_SSL_VERIFYPEER => false, // Eviter les soucis SSL avec trycloudflare
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    
    $body  = curl_exec($ch);
    $error = curl_error($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($body === false || !empty($error)) {
        return ['success' => false, 'error' => $error];
    }
    
    if ($code >= 200 && $code < 300) {
        return ['success' => true, 'body' => $body];
    }
    
    return ['success' => false, 'error' => "HTTP {$code}: {$body}"];
}

/**
 * Extrait un bloc JSON d'une réponse texte de l'IA.
 * L'IA peut entourer le JSON de texte explicatif ou de backticks.
 */
function extractJsonFromText(string $text): string {
    // Chercher un bloc JSON entre ```json et ```
    if (preg_match('/```json\s*([\s\S]+?)\s*```/i', $text, $matches)) {
        return $matches[1];
    }
    // Chercher entre ``` et ```
    if (preg_match('/```\s*([\s\S]+?)\s*```/i', $text, $matches)) {
        return $matches[1];
    }
    // Chercher le premier { ou [ jusqu'au dernier } ou ]
    if (preg_match('/(\{[\s\S]+\}|\[[\s\S]+\])/s', $text, $matches)) {
        return $matches[1];
    }
    return $text;
}

/**
 * Retourne le prompt système qui définit le rôle de l'IA
 * comme répétiteur expert des concours burkinabè.
 */
function getSystemPrompt(): string {
    return <<<PROMPT
Tu es "Grand Frère Intelligent", un répétiteur expert spécialisé dans la préparation aux concours 
de la fonction publique au Burkina Faso. Tu connais parfaitement :

1. Les concours de la Police Nationale (ENAPOSC), de l'ENAREF (fiscalité), de l'Enseignement 
   (ENEP, ENS), de la Santé publique, des Douanes, des Eaux et Forêts, de la Magistrature,
   de l'Armée et de la Gendarmerie nationale.

2. Les matières typiques : Français (expression écrite, résumé, dictée), Mathématiques, 
   Culture Générale burkinabè et africaine, Droit constitutionnel, Économie, Géographie,
   Histoire, Sciences, et les matières spécifiques à chaque concours.

3. Le contexte burkinabè : histoire du Burkina Faso, géographie, institutions, personnalités,
   culture, actualités nationales et africaines.

RÈGLES DE COMPORTEMENT :
- Réponds TOUJOURS en français clair et précis.
- Adapte le niveau de difficulté aux concours visés.
- Fournis des explications pédagogiques détaillées.
- Pour les quiz, génère du contenu en JSON valide uniquement si demandé.
- Encourage l'étudiant et sois bienveillant.
- Ne génère PAS de contenu offensant ou hors-sujet par rapport aux concours.
- Si l'utilisateur change de sujet (gossip, politique sensible), ramène-le poliment sur la préparation.

STYLE : Pédagogique, clair, motivant. Tu tutoies l'étudiant.
PROMPT;
}
