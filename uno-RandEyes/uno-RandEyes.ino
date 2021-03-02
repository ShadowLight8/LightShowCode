#include <IRremote.h>
#include <Servo.h>

Servo leftServo;
Servo rightServo;

IRrecv irrecv(11);     // create instance of 'irrecv'
decode_results results;

const int leftServo_MinPulse = 500;
const int leftServo_CenterPulse = 1500;
const int leftServo_MaxPulse = 2500;

const int rightServo_MinPulse = 500;
const int rightServo_CenterPulse = 1500;
const int rightServo_MaxPulse = 2500;

const int stepSize = 25;
const int minSpeedDelay = 30;
const int maxSpeedDelay = 75;

int priorLeftPulse = leftServo_CenterPulse;
int priorRightPulse = rightServo_CenterPulse;

bool moveEyes = false;

void setup()
{
  // Pin 9 - Left Servo
  // Pin 10 - Right Servo
  // Pin 11 - IR Reciever
  // Pin 13 - Green LED
  
  //Serial.begin(9600);
  //Serial.println("mHalloweenEyes"); 

  leftServo.attach(9, leftServo_MinPulse, leftServo_MaxPulse);
  rightServo.attach(10, rightServo_MinPulse, rightServo_MaxPulse);
  moveServo(leftServo_CenterPulse, rightServo_CenterPulse, minSpeedDelay);
 
  irrecv.enableIRIn(); // Start the receiver

  pinMode(13, OUTPUT);
  digitalWrite(13, HIGH);

  //delay(3000);
  //digitalWrite(13, LOW);
}

void loop()
{
  if (moveEyes)
  {
    int leftServoRandomPulse = random(leftServo_MinPulse, leftServo_MaxPulse);
    int rightServoRandomPulse = map(leftServoRandomPulse, leftServo_MinPulse, leftServo_MaxPulse, rightServo_MinPulse, rightServo_MaxPulse);

    //Serial.print("Left : Right Pulse "); 
    //Serial.print(leftServoRandomPulse);
    //Serial.print(" : ");
    //Serial.println(rightServoRandomPulse);
    
    int speedControl = random(minSpeedDelay, maxSpeedDelay);
    //Serial.print("Speed control: ");
    //Serial.println(speedControl);
  
    moveServo(leftServoRandomPulse, rightServoRandomPulse, speedControl);
  }

  int nextMoveDelay = random(5, 10);
  for (int i = 1; i < nextMoveDelay; i++)
  {
    if (irrecv.decode(&results) && results.decode_type == NEC)
    {
      if (moveEyes)
      {
        digitalWrite(13, HIGH);
        moveEyes = false;
        moveServo(leftServo_CenterPulse, rightServo_CenterPulse, minSpeedDelay);
        //delay(3000);
        //leftServo.detach();
        //rightServo.detach();
      } else {
        digitalWrite(13, LOW);  
        moveEyes = true;
        //leftServo.attach(9, leftServo_MinPulse, leftServo_MaxPulse);
        //rightServo.attach(10, rightServo_MinPulse, rightServo_MaxPulse);
        moveServo(leftServo_CenterPulse, rightServo_CenterPulse, minSpeedDelay);
        //delay(3000);
      }
    }
    irrecv.resume();
    delay(1000);
  }  
}

void moveServo(int leftPulse, int rightPulse, int speedDelay)
{
  if (priorLeftPulse > leftPulse)
  {
    for (int i = priorLeftPulse; i > leftPulse; i-=stepSize)
    {
      leftServo.writeMicroseconds(i);
      rightServo.writeMicroseconds(i);
      delay(speedDelay);
    }
  }
  else
  {
    for (int i = priorLeftPulse; i < leftPulse; i+=stepSize)
    {
      leftServo.writeMicroseconds(i);
      rightServo.writeMicroseconds(i);
      delay(speedDelay);
    }
  }
  
  leftServo.writeMicroseconds(leftPulse);
  rightServo.writeMicroseconds(rightPulse);
  priorLeftPulse = leftPulse;
  priorRightPulse = rightPulse;
}

