/*
 * ============================================================
 *  Sistem Monitoring Ternak Real-Time Berbasis Geofencing
 *  Firmware ESP32 + GPS NEO-6M
 * ============================================================
 *
 *  Cara pakai:
 *  1. Ganti WIFI_SSID, WIFI_PASS, SERVER_URL, dan DEVICE_TOKEN.
 *  2. Pastikan ESP32 dan komputer berada di jaringan yang sama.
 *  3. Buka Arduino IDE -> Board: ESP32 Dev Module.
 *  4. Upload, lalu buka Serial Monitor (115200 baud).
 *
 *  Wiring GPS NEO-6M -> ESP32:
 *    VCC  -> 3V3 (atau 5V bila modul memiliki regulator)
 *    GND  -> GND
 *    TX   -> GPIO16 (RX2)
 *    RX   -> GPIO17 (TX2)
 *
 *  Indikator LED (GPIO 2):
 *    - Kedip cepat  : gagal koneksi Wi-Fi
 *    - Nyala         : Wi-Fi terhubung, menunggu fix GPS
 *    - Kedip lambat  : GPS fix, data berhasil dikirim
 *    - Padam         : GPS belum fix / error kirim
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <TinyGPS++.h>
#include <HardwareSerial.h>

// ------------------------------------------------------------
// KONFIGURASI — ubah bagian ini
// ------------------------------------------------------------
const char* WIFI_SSID     = "NamaWiFi";
const char* WIFI_PASS     = "PasswordWiFi";

// Gunakan IP LAN komputer/server, bukan localhost.
const char* SERVER_URL    = "http://192.168.1.10/sistem monitoring ternak/api/telemetry.php";

// Salin token dari menu Perangkat IoT -> tombol copy.
const char* DEVICE_TOKEN  = "750624536b620133bbc0f3af17a0eff59a20db98afc77a97fc18938ea3e14855";

const unsigned long SEND_INTERVAL_MS = 10000;  // kirim tiap 10 detik
const unsigned long WIFI_RETRY_MS    = 5000;

// ------------------------------------------------------------
// Konfigurasi pin & objek
// ------------------------------------------------------------
const int PIN_LED     = 2;
const int GPS_RX_PIN  = 16;
const int GPS_TX_PIN  = 17;
const int GPS_BAUD    = 9600;

TinyGPSPlus gps;
HardwareSerial gpsSerial(2);

unsigned long lastSend     = 0;
unsigned long lastWifiTry  = 0;
bool          wasConnected = false;

// ------------------------------------------------------------
// LED helpers
// ------------------------------------------------------------
void ledOn()  { digitalWrite(PIN_LED, HIGH); }
void ledOff() { digitalWrite(PIN_LED, LOW); }

void blinkLed(int times, int delayMs) {
  for (int i = 0; i < times; i++) {
    ledOn();
    delay(delayMs);
    ledOff();
    delay(delayMs);
  }
}

// ------------------------------------------------------------
// Wi-Fi
// ------------------------------------------------------------
void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) {
    return;
  }

  Serial.print("[WiFi] Menghubungkan ke ");
  Serial.println(WIFI_SSID);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 15000) {
    blinkLed(1, 150);
    delay(250);
    Serial.print(".");
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("[WiFi] Terhubung. IP: ");
    Serial.println(WiFi.localIP());
    ledOn();
  } else {
    Serial.println("[WiFi] Gagal terhubung. Akan dicoba lagi.");
    ledOff();
  }
}

// ------------------------------------------------------------
// Kirim payload JSON ke server
// ------------------------------------------------------------
bool sendTelemetry(double lat, double lng, double accuracy) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[HTTP] Wi-Fi terputus, membatalkan pengiriman.");
    return false;
  }

  HTTPClient http;
  http.begin(SERVER_URL);
  http.setTimeout(12000);
  http.setConnectTimeout(8000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Authorization", String("Bearer ") + DEVICE_TOKEN);

  char payload[220];
  snprintf(
    payload,
    sizeof(payload),
    "{\"latitude\":%.7f,\"longitude\":%.7f,\"accuracy\":%.1f,\"satellites\":%d,\"hdop\":%.2f}",
    lat,
    lng,
    accuracy,
    gps.satellites.isValid() ? gps.satellites.value() : 0,
    gps.hdop.isValid() ? gps.hdop.hdop() : 0.0
  );

  Serial.print("[HTTP] POST ");
  Serial.println(payload);

  int statusCode = http.POST((uint8_t*)payload, strlen(payload));
  String response = http.getString();

  Serial.print("[HTTP] Status: ");
  Serial.println(statusCode);
  Serial.print("[HTTP] Respons: ");
  Serial.println(response);

  http.end();

  if (statusCode == 200 || statusCode == 201) {
    blinkLed(2, 80);
    return true;
  }

  ledOff();
  return false;
}

// ------------------------------------------------------------
// Setup
// ------------------------------------------------------------
void setup() {
  Serial.begin(115200);
  delay(300);

  pinMode(PIN_LED, OUTPUT);
  ledOff();

  Serial.println();
  Serial.println("==============================================");
  Serial.println("  TernakTrack - GPS Tracker ESP32");
  Serial.println("==============================================");

  gpsSerial.begin(GPS_BAUD, SERIAL_8N1, GPS_RX_PIN, GPS_TX_PIN);
  Serial.println("[GPS] Menunggu sinyal satelit...");

  connectWiFi();
}

// ------------------------------------------------------------
// Loop
// ------------------------------------------------------------
void loop() {
  // Baca data GPS secara non-blocking.
  while (gpsSerial.available() > 0) {
    gps.encode(gpsSerial.read());
  }

  // Jaga koneksi Wi-Fi dengan auto-reconnect.
  if (WiFi.status() != WL_CONNECTED) {
    if (wasConnected) {
      Serial.println("[WiFi] Koneksi terputus.");
      wasConnected = false;
      ledOff();
    }

    if (millis() - lastWifiTry > WIFI_RETRY_MS) {
      lastWifiTry = millis();
      connectWiFi();
    }

    return;
  }

  wasConnected = true;

  if (millis() - lastSend < SEND_INTERVAL_MS) {
    return;
  }
  lastSend = millis();

  if (!gps.location.isValid() || !gps.location.isUpdated()) {
    Serial.println("[GPS] Signal Lost: belum ada fix. Data tidak dikirim.");
    ledOff();
    return;
  }

  double lat = gps.location.lat();
  double lng = gps.location.lng();

  // Tolak koordinat (0,0) yang menandakan GPS belum lock.
  if (fabs(lat) < 0.0000001 && fabs(lng) < 0.0000001) {
    Serial.println("[GPS] Koordinat tidak valid (0,0).");
    ledOff();
    return;
  }

  double accuracy = gps.hdop.isValid() ? gps.hdop.hdop() * 5.0 : 10.0;

  Serial.print("[GPS] Fix: ");
  Serial.print(lat, 7);
  Serial.print(", ");
  Serial.println(lng, 7);

  sendTelemetry(lat, lng, accuracy);
}
