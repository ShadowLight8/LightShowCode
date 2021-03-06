#!/usr/bin/perl
#############################################################################
# PixelOverlay-Countdown.pl - Scroll a Christmas Countdown across a matrix
#############################################################################
# Set our library path to find the FPP Perl modules
use lib "/opt/fpp/lib/perl/";

# Use the FPP Memory Map module to talk to the daemon
use FPP::MemoryMap;

use Time::Piece;

#############################################################################
# Setup some variables (this is the part you want to edit for font, color, etc.)
my $name  = "Matrix_Overlay";    # Memory Mapped block name
my $color = "#FF00F0";      # Text Color (also names like 'red', 'blue', etc.)
my $fill  = "#000000";      # Fill color (not used currently)
my $font  = "DejaVu-Sans-Book";        # Font Name
my $size  = "22";           # Font size
my $pos   = "0,10";       # Position: 'scroll', 'center', 'x,y' (ie, '10,20')
my $dir   = "R2L";          # Scroll Direction: 'R2L', 'L2R', 'T2B', 'B2T'
my $pps   = 10;              # Pixels Per Second

# Must include timezone since localtime(time) does
my $t_ShowTime =  Time::Piece->strptime(localtime(time)->ymd.' 21:00:00 -0400', '%Y-%m-%d %H:%M:%S %z');

our $keepRunning = 1;

#############################################################################
# This function will get called once per second and returns the text string
# to be displayed at that point in time.
sub GetNextMessage
{
        my $wait = $t_ShowTime - localtime(time);

        if ($wait < 0)
        {
                $keepRunning = 0;
                return "";
        }

        my $min = int($wait->minutes);
        $wait -= $min * 60;

        my $msg = sprintf "%02d:%02d", $min, int($wait->seconds);

#       print $msg."\n";
        return $msg;
}

#############################################################################
# Main part of program

# Instantiate a new instance of the MemoryMap interface
my $fppmm = new FPP::MemoryMap;

# Open the maps
$fppmm->OpenMaps();

# Get info about the block we are interested in
my $blk = $fppmm->GetBlockInfo($name);

$fppmm->SetBlockColor($blk, 0, 0, 0);

$fppmm->TextMessage($blk, "Next show", "#FF3000", "#000000", $font, "12", "-2,-2", $dir, $pps);

# Enable the block (pass 2 for transparent mode, or 3 for transparent RGB)
$fppmm->SetBlockState($blk, 1);

# Loop forever (ie, you'll need to CTRL-C to stop this script or kill it)
while ($keepRunning) {
        $fppmm->TextMessage($blk, \&GetNextMessage, $color, $fill, $font, $size, $pos, $dir, $pps);
}

$fppmm->SetBlockColor($blk, 0, 0, 0);
sleep(1);

# Disable the block
$fppmm->SetBlockState($blk, 0);

# Close the maps (shouldn't make it here with the above "while (1)" loop)
$fppmm->CloseMaps();

# Exit cleanly (shouldn't make it here with the above "while (1)" loop)
exit(0);

#############################################################################
