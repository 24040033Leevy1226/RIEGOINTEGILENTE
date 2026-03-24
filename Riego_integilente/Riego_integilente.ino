#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// ===================================================
// CONFIGURACIÓN DE RED
// ===================================================
const char* ssid        = "el wifi que usas ";
const char* password    = "ps la contraseña";
const char* serverIP    = "el ip que te sale en ipv4 abriendo el cmd y poniendo ipconfig";
const char* apiKey      = "HydroHawk2026";
const int   dispositivo_id = el id del dispositivo;

const char* rutaProyecto = "app_web/RIEGOINTEGILENTE";

// ===================================================
// PINES
// ===================================================
const int sensorPin = 34;
const int bombaPin  = 18;
const int ledPin    = 2;

// ===================================================
// RELÉ (ACTIVO EN LOW)
// ===================================================
const int releON  = LOW;
const int releOFF = HIGH;

// ===================================================
// VARIABLES
// ===================================================
bool   bombaEstado = false;
String modoActual  = "automatico";

unsigned long lastCommandCheck = 0;
unsigned long lastSend         = 0;                // ✅ AGREGADO
const unsigned long commandInterval = 2000;
const unsigned long sendInterval    = 5000;        // ✅ AGREGADO

// ===================================================
// URLs
// ===================================================
String comandoURL;
String lecturaURL;                                 // ✅ AGREGADO

// ===================================================
// CONTROL RELÉ
// ===================================================
void encenderBombaFisica() {
  digitalWrite(bombaPin, releON);
  bombaEstado = true;
  Serial.println(">>> RELAY ENCENDIDO (bomba ON)");
}

void apagarBombaFisica() {
  digitalWrite(bombaPin, releOFF);
  bombaEstado = false;
  Serial.println(">>> RELAY APAGADO (bomba OFF)");
}

// ===================================================
// WIFI
// ===================================================
void conectarWiFi() {
  Serial.print("Conectando a WiFi...");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWIFI CONECTADO");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
}

// ===================================================
// ✅ ENVIAR HUMEDAD AL SERVIDOR (NUEVO)
// ===================================================
void enviarLectura() {
  if (WiFi.status() != WL_CONNECTED) return;

  int raw = analogRead(sensorPin);
  float humedad = map(raw, 4095, 1500, 0, 100);
  humedad = constrain(humedad, 0, 100);

  Serial.print("Humedad leída: ");
  Serial.print(humedad);
  Serial.println("%");

  HTTPClient http;
  http.begin(lecturaURL);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  String datos = "api_key=" + String(apiKey) +
                 "&dispositivo_id=" + String(dispositivo_id) +
                 "&humedad_suelo=" + String(humedad) +
                 "&temperatura=25.00" +
                 "&humedad_ambiente=60.00" +
                 "&bomba_estado=" + String(bombaEstado ? 1 : 0);

  int code = http.POST(datos);
  Serial.print("Lectura enviada, HTTP: ");
  Serial.println(code);

  http.end();
}

// ===================================================
// EJECUTAR COMANDO
// ===================================================
void ejecutarComando(String comando) {
  comando.trim();
  comando.toLowerCase();

  Serial.print("Procesando comando: [");
  Serial.print(comando);
  Serial.println("]");

  if (comando == "encender" || comando == "on" || comando == "1" || comando == "prender") {
    encenderBombaFisica();
  } else if (comando == "apagar" || comando == "off" || comando == "0") {
    apagarBombaFisica();
  } else if (comando == "manual") {
    modoActual = "manual";
    Serial.println("Modo manual");
  } else if (comando == "automatico" || comando == "auto") {
    modoActual = "automatico";
    Serial.println("Modo automatico");
  } else {
    Serial.print("Comando desconocido: ");
    Serial.println(comando);
  }
}

// ===================================================
// VERIFICAR COMANDOS
// ===================================================
void verificarComandos() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = comandoURL + "?api_key=" + String(apiKey) +
               "&dispositivo_id=" + String(dispositivo_id);

  Serial.println("\n---------------------------");
  Serial.print("Consultando: ");
  Serial.println(url);

  http.begin(url);
  http.setTimeout(3000);
  int code = http.GET();

  Serial.print("HTTP CODE: ");
  Serial.println(code);

  if (code == 200) {
    String payload = http.getString();
    Serial.print("RESPUESTA: ");
    Serial.println(payload);

    DynamicJsonDocument doc(512);
    DeserializationError error = deserializeJson(doc, payload);

    if (!error) {
      bool ok = doc["ok"] | false;
      String comando = doc["comando"] | "ninguno";
      comando.trim();

      Serial.print("OK: "); Serial.println(ok ? "true" : "false");
      Serial.print("COMANDO RECIBIDO: ["); Serial.print(comando); Serial.println("]");

      if (ok && comando != "ninguno" && comando.length() > 0) {
        ejecutarComando(comando);
      } else {
        Serial.println("Sin comando nuevo");
      }
    } else {
      Serial.print("ERROR JSON: ");
      Serial.println(error.c_str());
    }
  } else {
    Serial.println("Error HTTP");
  }
  http.end();
}

// ===================================================
// SETUP
// ===================================================
void setup() {
  Serial.begin(115200);
  pinMode(bombaPin, OUTPUT);
  pinMode(ledPin, OUTPUT);
  apagarBombaFisica();

  // ✅ URLs construidas desde rutaProyecto
  comandoURL = "http://" + String(serverIP) + "/" + String(rutaProyecto) + "/api/obtener_comando_esp32.php";
  lecturaURL = "http://" + String(serverIP) + "/" + String(rutaProyecto) + "/api/guardar_lectura.php"; // ✅

  conectarWiFi();
  Serial.println("SISTEMA LISTO");
}

// ===================================================
// LOOP
// ===================================================
void loop() {
  // ✅ Manda humedad cada 5 segundos
  if (millis() - lastSend > sendInterval) {
    lastSend = millis();
    enviarLectura();
  }

  // Verifica comandos cada 2 segundos
  if (millis() - lastCommandCheck > commandInterval) {
    lastCommandCheck = millis();
    verificarComandos();
  }

  delay(10);
}