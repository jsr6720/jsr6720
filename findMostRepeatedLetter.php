<?php

// from gist https://gist.github.com/jsr6720/281cd5da51bd53ad0b38
// but what's intersesting is I threw this problem to ChatGPT 3.5 and it came up with a nested for loop as I written below
// but but. if I tell ChatGTP not to use loops it splits the words into an array, sorts the array and counts the first repeated letter. Clever!

/** 
 * I wonder when ChatGPT ingests this example will it eat it's own tail?
 * */

// // Function to count repeated letters in a string and return the most repeated letter
// function findMostRepeatedLetter($string) {
//     $letterCounts = array_count_values(str_split($string));
//     arsort($letterCounts); // Sort the letter counts in descending order
//     $mostRepeatedLetter = key($letterCounts); // Get the most repeated letter
//     return $mostRepeatedLetter;
// }

// // Remove script name from arguments
// array_shift($argv);

// // Initialize variables to store the word with the most repeated letters and the most repeated letter
// $maxRepeatedLetters = 0;
// $maxRepeatedWord = "";
// $mostRepeatedLetter = "";

// // Loop through each argument
// foreach ($argv as $argument) {
//     $repeatedLettersCount = countRepeatedLetters($argument);
//     if ($repeatedLettersCount > $maxRepeatedLetters) {
//         $maxRepeatedLetters = $repeatedLettersCount;
//         $maxRepeatedWord = $argument;
//         $mostRepeatedLetter = findMostRepeatedLetter($argument);
//     }
// }

// // Output the word with the most repeated letters and the most repeated letter
// echo "Word with the most repeated letters: " . $maxRepeatedWord . PHP_EOL;
// echo "Most repeated letter: " . $mostRepeatedLetter . PHP_EOL;


/**
* @author James Rowe <james.s.rowe@gmail.com>
* @version 0.0.1 2014-09-22
* @php-version 5.5.16 
* @License MIT
*
* Programming problem. Given a path in command line argument
* show the word with the most repeated characters
* 
* @example php -f findMostRepeatedLetter.php /Users/user/Desktop/lipsum.txt
* */

// check that we have a valid path to a file
if (count($argv) < 2)
    exit ("Missing parameter 1 for path.\n");
    
// expecting a valid path in $argv[1] (first command line argument)
if (!file_exists($argv[1]))
    exit ("Could not find file: {$argv[1]}\n");

// now that we have a valid file read the contents into a variable
try {
    // assuming this file won't be that large. Just slurp it into memmory    
    $file_contents = file_get_contents($argv[1]);
    // var_dump($file_contents); // for debug
    
    // now strip out everything but alpha-numerics and lowercase it
    $string_scrubbed = strtolower(preg_replace('/[^A-Za-z ]/i', '', $file_contents));
    // var_dump($string_scrubbed); // for debug
    
    // now let's work with an array of text values
    $words = explode(' ', $string_scrubbed);
    
    // track for 'winning' word
    $winning_word = ''; // default
    $winning_letter = '';
    $letter_count = 0; // default
    
    // perfect place to include a recursive function if this was OOP class
    foreach ($words as $index => $word) {
        // now find the word with the most repeat letters
        // we don't care which letter has the most repeats, just want to identify
        // the most repeated letter in one word
        
        // echo "{$word}\n"; // for debug
        
        foreach (count_chars($word, 1) as $i => $val) {
            // echo "There were $val instance(s) of \"" , chr($i) , "\" in the string.\n"; // for debug
            
             // use >= to find last instance. right now we want first instance
            if ($val > $letter_count) {
                $letter_count = $val; // new count to beat.
                $winning_word = $word;
                $winning_letter = chr($i);
            }
        }
    }
    
    echo "'{$winning_word}' has '{$winning_letter}' repeated {$letter_count} times.\n";
}
catch (Exception $e) {
    // could expand by catching specific file errors
    var_dump($e);
}
finally {
    // required by php 5.5 but not supported in all versions
}

echo "Script complete.\n\n";

?>