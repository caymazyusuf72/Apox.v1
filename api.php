<?php
// Hata raporlamayı aç (geliştirme aşamasında faydalı)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Yalnızca POST istekleri kabul edilir.']);
    exit;
}

// .env dosyasını okuyan fonksiyon (değişiklik yok)
function loadEnv(string $path): void {
    if (!file_exists($path) || !is_readable($path)) {
        throw new \RuntimeException(sprintf('"%s" dosyası bulunamadı veya okunamıyor.', $path));
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim(trim($value), '"\'');
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
        }
    }
}

function callGeminiApi(array $history, string $apiKey): string {
    // --- MODEL İSTEĞİNİZ ÜZERİNE GÜNCELLENDİ ---
    // DİKKAT: Bu model adı muhtemelen "Model not found" hatası verecektir.
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;
    
    $data = ['contents' => $history];
    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Hatası: " . $error_msg);
    }
    curl_close($ch);

    if ($httpCode !== 200) {
        $decodedResponse = json_decode($response, true);
        $errorMessage = $decodedResponse['error']['message'] ?? $response;
        throw new Exception("API Hatası (HTTP {$httpCode}): " . $errorMessage);
    }

    $result = json_decode($response, true);
    if (empty($result['candidates'][0]['content']['parts'][0]['text'])) {
        return "Üzgünüm, bu isteğe yanıt oluşturamadım. Lütfen farklı bir şekilde tekrar dene.";
    }
    $text = $result['candidates'][0]['content']['parts'][0]['text'];
    $text = preg_replace('/^```(html|svg)?\s*|\s*```$/', '', $text);
    return trim($text);
}


try {
    loadEnv(__DIR__ . '/.env');
    $apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');

    if (empty($apiKey) || $apiKey === 'BURAYA_KENDİ_API_ANAHTARINIZI_YAPIŞTIRIN') {
        throw new Exception('.env dosyasında geçerli bir GEMINI_API_KEY bulunamadı.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $chatHistory = $input['history'] ?? [];
    if (empty($chatHistory)) {
        throw new Exception('Sohbet geçmişi boş olamaz.');
    }
    
    $masterCodePrompt = "Sen, modern ve eğlenceli web tabanlı oyunlar üreten uzman bir oyun geliştiricisisin. Sana verilen oyun fikrini, aşağıdaki kurallara uyarak tek bir HTML dosyası olarak hayata geçir: 1. **Yapısal Kod:** JavaScript kodunu ES6 class yapısını (örneğin Player, Enemy, Game sınıfları) kullanarak organize et. Bu, kodun okunabilirliğini ve yönetilebilirliğini artırır. 2. **Oyun Döngüsü:** `requestAnimationFrame` kullanarak akıcı bir oyun döngüsü (`update` ve `draw` fonksiyonları) oluştur. 3. **Grafikler:** Sadece basit kareler kullanma. Renk geçişleri (gradient), gölgeler veya daha karmaşık şekiller kullanarak oyunu görsel olarak çekici hale getir. 4. **Oynanış:** Oyunda bir amaç olmalı. Puanlama sistemi, 'Game Over' durumu ve oyunu yeniden başlatma (örneğin 'R' tuşuna basarak) mekanizması ekle. 5. **Kullanıcı Arayüzü (UI):** Puan, kalan can gibi bilgileri canvas üzerine net bir şekilde yazdır. 'Game Over' ekranını belirgin bir şekilde göster. 6. **Sadece Kod:** Çıktı olarak SADECE ve SADECE tam HTML kodunu ver. Kodun başına veya sonuna markdown (```html) veya herhangi bir açıklama ekleme. 7. **Sohbeti Hatırla:** Eğer önceki mesajlarda bir oyun oluşturduysan ve yeni istek bu oyunu değiştirmeye yönelikse (örn: 'rengi değiştir', 'hızlandır'), yeni bir oyun yapmak yerine mevcut oyun kodunu bu isteğe göre GÜNCELLE.";

    $lastUserMessage = end($chatHistory)['parts'][0]['text'];
    
    $logoPromptHistory = [['role' => 'user', 'parts' => [['text' => "Bana '{$lastUserMessage}' fikrine uygun, SADECE SVG kodu olarak bir logo ver."]]]];
    $logoSvg = callGeminiApi($logoPromptHistory, $apiKey);

    $codeGenerationHistory = $chatHistory;
    array_unshift($codeGenerationHistory, ['role' => 'user', 'parts' => [['text' => $masterCodePrompt]]], ['role' => 'model', 'parts' => [['text' => "Anlaşıldı. Belirtilen kurallara uyarak, modern bir oyun kodu üreteceğim."]]]);
    $gameCode = callGeminiApi($codeGenerationHistory, $apiKey);

    echo json_encode([
        'success' => true,
        'logoSvg' => $logoSvg,
        'gameCode' => $gameCode
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}