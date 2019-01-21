// Created by Nick Anderson 8/12/2018
// Used to control the Eye servos

// Listen to one E1.31 Universe for the first 2 channels
// Each channel has data from 0 to 255
// If the data is 0, do nothing
// If the data is 1-127, map the channel value from *_MinPulse to *_CenterPulse
// If the data is 128-255, map the channel value from *_CenterPulse to *_MaxPulse

#include <ESP8266WiFi.h> // From https://github.com/esp8266/Arduino
#include <ESPAsyncE131.h> // From https://github.com/forkineye/ESPAsyncE131
#include <Servo.h> // From https://github.com/esp8266/Arduino
// In Servo.h, changed REFRESH_INTERVAL from 20000 to 5000 since it seemed to help, but isn't critical

#define UNIVERSE 1 // Be sure to update the UNIVERSE number
#define UNIVERSE_COUNT 1

const char ssid[] = "SET-SSID";
const char passphrase[] = "SET-PASSWORD";

Servo leftServo;
Servo rightServo;
int leftServoPin = 5; // D1
int rightServoPin = 4; // D2

// These 6 variables allow for some basic calibration. Even with two of the same model servos, they reacts a little bit
// different to the pulse length and the position they move to. This helps keep the output angles the same.

// _MinPulse and _MaxPulse defines the values that yield the same respective positions
// _CenterPulse defines the middle position for the servo

const int leftServo_MinPulse = 600;
const int leftServo_CenterPulse = 1500;
const int leftServo_MaxPulse = 2500;

const int rightServo_MinPulse = 500;
const int rightServo_CenterPulse = 1500;
const int rightServo_MaxPulse = 2350;

ESPAsyncE131 e131(UNIVERSE_COUNT);

void setup() {
  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, HIGH);

  pinMode(2, OUTPUT);
  digitalWrite(2, HIGH);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, passphrase);

  // Fast blink until WiFi connected
  while(WiFi.status() != WL_CONNECTED ) {
    digitalWrite(2, !digitalRead(2));
    delay(200);
  }

  digitalWrite(2, LOW);

  e131.begin(E131_UNICAST);

  leftServo.attach(leftServoPin, leftServo_MinPulse, leftServo_MaxPulse);
  rightServo.attach(rightServoPin, rightServo_MinPulse, rightServo_MaxPulse);

  // Quick test of the servos
  for (uint16_t i = 500; i <= 1500; i += 10) {
    leftServo.writeMicroseconds(i);
    rightServo.writeMicroseconds(i);
    delay(20);
  }
  delay(1000);
}

e131_packet_t packet;
uint8_t leftLastValue = 0;
uint16_t leftPulse = 1500;
uint8_t rightLastValue = 0;
uint16_t rightPulse = 1500;
uint8_t packetWatch = 0;

void loop() {
  if (!e131.isEmpty()) {
    e131.pull(&packet);

    // Blink to indicate data being received every 60 packets
    packetWatch = ++packetWatch % 60;
    switch (packetWatch) {
      case 0:
        digitalWrite(2, LOW);
        break;
      case 1:
        digitalWrite(2, HIGH);
    }

    if (packet.property_values[1] != 0 || packet.property_values[2] != 0)
    {
      leftLastValue = packet.property_values[1];
      rightLastValue = packet.property_values[2];
      leftPulse = leftLastValue <= 127 ? map(leftLastValue, 1, 127, leftServo_MinPulse, leftServo_CenterPulse) : map(leftLastValue, 128, 255, leftServo_CenterPulse, leftServo_MaxPulse);
      rightPulse = rightLastValue <= 127 ? map(rightLastValue, 1, 127, rightServo_MinPulse, rightServo_CenterPulse) : map(rightLastValue, 128, 255, rightServo_CenterPulse, rightServo_MaxPulse);
      leftServo.writeMicroseconds(leftPulse);
      rightServo.writeMicroseconds(rightPulse);
      
      // Blink fast when changing values
      digitalWrite(LED_BUILTIN, !digitalRead(LED_BUILTIN));
    }
  }
  delay(0);
}
