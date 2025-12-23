#!/usr/bin/php
<?php
	## BUG TO FIX: Countdown didn't work right on 11/1

    #############################################################################
    # Setup some variables (this is the part you want to edit for font, color, etc.)
    $host  = "localhost";    # Host/ip of the FPP instance with the matrix
    $name  = "Matrix_Overlay_Bottom";       # PixelOverlay Model Name
    $color = "#8000FF";      # Text Color (also names like 'red', 'blue', etc.)
    $font  = "Helvetica";    # Font Name
    $size  = 46;             # Font size
    $pos   = "Center";          # Position: 'Center', 'L2R', 'R2L', 'T2B', 'B2T'
    $pps   = 0;              # Pixels Per Second
    $antiAlias = true;      # Anti-Alias the text

    $name_top  = "Matrix_Overlay_Top";       # PixelOverlay Model Name
    $color_top = "#FF4000";      # Text Color (also names like 'red', 'blue', etc.)
    $size_top  = 26;             # Font size
    $antiAlias_top = true;      # Anti-Alias the text
    $msg_top = "Next Show";

    # Date range in October sets start time 7pm -> 6:30pm from the 24th -> 6pm on the 31st
    $Showtime = new DateTime('20:00');
    if (date('j') >= 31)
        $Showtime = new DateTime('18:00');
    elseif (date('j') >= 9)
        $Showtime = new DateTime('19:00');
    $Showtime = $Showtime->getTimestamp();

    #############################################################################
    # This function will get called once per second and returns the text string
    # to be displayed at that point in time.
    function GetNextMessage() {
        global $Showtime, $size, $running;

        $diff = $Showtime - (new DateTime())->getTimestamp();

	# Trigger to stop script when we're at the correc time
	if ($diff <= 0)
	    $running = false;

	$diff += 89; // Add offset to line up with first song countdown

        $minsDiff = (int)($diff / 60);
        $secsDiff = $diff % 60;

        # Countdown timer like "88:88" or "1:11"
	$message = sprintf("%d:%02d", $minsDiff, $secsDiff);
	# Option to resize when countdown is longer than 99:59
	if (strlen($message) > 5)
	    $size = 42;
	else
	    $size = 46;
	# echo 'msg - ',$message,"\n";
        return $message;
    }

    $ch = curl_init();
    function do_put($url, $data) {
        //Initiate cURL.
        global $ch;

        //Tell cURL that we want to send a PUT request.
        //Attach our encoded JSON string to the PUT fields.
        //Set the content type to application/json
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [ 'Content-Type' => 'application/json' ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        //Execute the request
        $result = curl_exec($ch);

        return $result;
    }

    #############################################################################
    # setup a signal handler to clear the screen if ctrl-c is used to
    # stop the script
    $running = true;
    declare(ticks=1); // PHP internal, make signal handling work
    function signalHandler($signo)  {
	global $running;
        $running = false;
    }
    pcntl_signal(SIGINT, 'signalHandler');

    #############################################################################
    # Main part of program

    # Clear the block, probably not necessary
    file_get_contents("http://$host/api/overlays/model/$name_top/clear");
    file_get_contents("http://$host/api/overlays/model/$name/clear");

    # Enable the block (pass 2 for transparent mode, or 3 for transparent RGB)
    $data = array( 'State' => 1 );
    do_put("http://$host/api/overlays/model/$name_top/state", json_encode($data));
    do_put("http://$host/api/overlays/model/$name/state", json_encode($data));

    $data = array('Message' => $msg_top,
                  'Color' => $color_top,
                  'Font' => $font,
                  'FontSize' => $size_top,
                  'AntiAlias' => $antiAlias_top,
                  'Position' => $pos,
                  'PixelsPerSecond' => $pps);
    do_put("http://$host/api/overlays/model/$name_top/text", json_encode($data));

    # Loop forever (ie, you'll need to CTRL-C to stop this script or kill it)
    while (1) {
	# echo '1: ',date("H:i:s"),' --- ',microtime(true),"\n";
        $data = array('Message' => GetNextMessage(),
                      'Color' => $color,
                      'Font' => $font,
                      'FontSize' => $size,
                      'AntiAlias' => $antiAlias,
                      'Position' => $pos,
                      'PixelsPerSecond' => $pps);
        do_put("http://$host/api/overlays/model/$name/text", json_encode($data));
	if (!$running) break;
	# echo '2: ',date("H:i:s"),' --- ',microtime(true),"\n";
	$delay = 1000000 - (int)(1000000*(microtime(true)-time()));
	# echo '3: ',$delay,"\n";
        usleep($delay);
    }

    # Clear the block
    # sleep(2);

    # Disable the block
    $data = array('State' => 0);
    file_get_contents("http://$host/api/overlays/model/$name/clear");
    do_put("http://$host/api/overlays/model/$name/state", json_encode($data));

    file_get_contents("http://$host/api/overlays/model/$name_top/clear");
    do_put("http://$host/api/overlays/model/$name_top/state", json_encode($data));


    # Exit cleanly (shouldn't make it here with the above "while (1)" loop)
    exit(0);
    #############################################################################
?>

