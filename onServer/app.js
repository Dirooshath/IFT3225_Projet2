// Global variables
let currentUser = null;
let wordGameTimer = null;
let defGameTimer = null;
let wordGameData = null;
let defGameData = null;

// Router configuration
const routes = {
    'home': showHomePage,
    'login': showLoginPage,
    'register': showRegisterPage,
    'play-word': showWordGamePage,
    'create-def': showDefGamePage,
    'view-defs': showDefsPage
};

// Initialize application
$(document).ready(function() {
    // Set up hash-based routing
    window.addEventListener('hashchange', handleRouteChange);
    
    // Check for existing session
    checkUserSession();
    
    // Initial route handling
    handleRouteChange();
    
    // Set up event handlers
    setupEventHandlers();
    
    // Load leaderboard data
    loadLeaderboard();
});

// Check if user is already logged in (simulated here)
function checkUserSession() {
    const storedUser = localStorage.getItem('currentUser');
    if (storedUser) {
        currentUser = JSON.parse(storedUser);
        updateUIForLoggedInUser();
    }
}

// Handle route changes
function handleRouteChange() {
    const hash = window.location.hash.substring(1) || 'home';
    const routeHandler = routes[hash];
    
    // Hide all pages
    $('.page-container').removeClass('active-page');
    
    if (routeHandler) {
        routeHandler();
    } else {
        // Default to home page if route not found
        showHomePage();
    }
}

// Set up all event handlers
function setupEventHandlers() {
    // Login form submission
    $('#login-form').on('submit', function(e) {
        e.preventDefault();
        handleLogin();
    });
    
    // Registration form submission
    $('#register-form').on('submit', function(e) {
        e.preventDefault();
        handleRegistration();
    });
    
    // Logout button
    $('#logout-btn').on('click', handleLogout);
    
    // Word game buttons
    $('#guess-word-btn').on('click', handleGuessWord);
    $('#hint-btn').on('click', handleHintRequest);
    $('#new-word-game-btn').on('click', startNewWordGame);
    
    // Definition game buttons
    $('#submit-def-btn').on('click', handleSubmitDefinition);
    $('#new-def-game-btn').on('click', startNewDefGame);
}

// Page display functions
function showHomePage() {
    $('#home-page').addClass('active-page');
    
    // Update welcome message based on login status
    if (currentUser) {
        $('#welcome-message').html(`Welcome back, <strong>${currentUser.username}</strong>! Choose a game to play.`);
    } else {
        $('#welcome-message').text('Welcome to the Word Game! Please login or register to start playing.');
    }
}

function showLoginPage() {
    $('#login-page').addClass('active-page');
}

function showRegisterPage() {
    $('#register-page').addClass('active-page');
}

function showWordGamePage() {
    $('#play-word-page').addClass('active-page');
    
    // If user is not logged in, redirect to login
    if (!currentUser) {
        window.location.hash = 'login';
        return;
    }
    
    // Start a new word game if there's no active game
    if (!wordGameData) {
        startNewWordGame();
    }
}

function showDefGamePage() {
    $('#create-def-page').addClass('active-page');
    
    // If user is not logged in, redirect to login
    if (!currentUser) {
        window.location.hash = 'login';
        return;
    }
    
    // Start a new definition game if there's no active game
    if (!defGameData) {
        startNewDefGame();
    }
}

function showDefsPage() {
    $('#view-defs-page').addClass('active-page');
    
    // Initialize DataTable if not already done
    if (!$.fn.DataTable.isDataTable('#definitions-table')) {
        initDefinitionsTable();
    }
}

// Authentication Functions
function handleLogin() {
    const username = $('#login-username').val();
    const password = $('#login-password').val();
    
    if (!username || !password) {
        showLoginMessage('Please enter both username and password.', 'danger');
        return;
    }
    
    // Actual API call to login
    $.ajax({
        url: 'api.php?path=gamers/login/' + encodeURIComponent(username) + '/' + encodeURIComponent(password),
        method: 'GET',
        success: function(response) {
            currentUser = {
                username: username,
                score: response.score || 0
            };
            
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            updateUIForLoggedInUser();
            
            // Redirect to home page
            window.location.hash = 'home';
        },
        error: function(xhr) {
            if (xhr.responseJSON && xhr.responseJSON.error) {
                showLoginMessage(xhr.responseJSON.error, 'danger');
            } else {
                showLoginMessage('Login failed. Please try again.', 'danger');
            }
        }
    });
}

function handleRegistration() {
    const username = $('#register-username').val();
    const password = $('#register-password').val();
    const confirm = $('#register-confirm').val();
    
    if (!username || !password || !confirm) {
        showRegisterMessage('Please fill out all fields.', 'danger');
        return;
    }
    
    if (password !== confirm) {
        showRegisterMessage('Passwords do not match.', 'danger');
        return;
    }
    
    // Actual API call to register
    $.ajax({
        url: 'api.php?path=gamers/add/' + encodeURIComponent(username) + '/' + encodeURIComponent(password),
        method: 'GET',
        success: function(response) {
            showRegisterMessage('Registration successful! You can now login.', 'success');
            
            // Clear the form
            $('#register-form')[0].reset();
            
            // Redirect to login page after a short delay
            setTimeout(function() {
                window.location.hash = 'login';
            }, 1500);
        },
        error: function(xhr) {
            if (xhr.responseJSON && xhr.responseJSON.error) {
                showRegisterMessage(xhr.responseJSON.error, 'danger');
            } else {
                showRegisterMessage('Registration failed. Please try again.', 'danger');
            }
        }
    });
}

function handleLogout() {
    // Actual API call to logout
    $.ajax({
        url: 'api.php?path=gamers/logout/' + encodeURIComponent(currentUser.username) + '/password',
        method: 'GET',
        success: function(response) {
            currentUser = null;
            localStorage.removeItem('currentUser');
            updateUIForLoggedOutUser();
            
            // Redirect to home page
            window.location.hash = 'home';
            
            // Clear any active games
            clearWordGame();
            clearDefGame();
        },
        error: function(xhr) {
            console.error('Logout error:', xhr);
            // Even if the logout fails on the server, still log out locally
            currentUser = null;
            localStorage.removeItem('currentUser');
            updateUIForLoggedOutUser();
            window.location.hash = 'home';
        }
    });
}

function updateUIForLoggedInUser() {
    // Update navigation
    $('#user-display').show();
    $('#username-display').text(currentUser.username);
    $('#login-nav-btn, #register-nav-btn').hide();
    $('#logout-btn').show();
    
    // Update game player names
    $('#word-game-player, #def-game-player').text(currentUser.username);
}

function updateUIForLoggedOutUser() {
    // Update navigation
    $('#user-display').hide();
    $('#login-nav-btn, #register-nav-btn').show();
    $('#logout-btn').hide();
    
    // Update game player names
    $('#word-game-player, #def-game-player').text('Guest');
}

function showLoginMessage(message, type) {
    const messageDiv = $('#login-message');
    messageDiv.text(message);
    messageDiv.removeClass('alert-danger alert-success').addClass('alert-' + type);
    messageDiv.show();
    
    // Hide the message after a few seconds
    setTimeout(function() {
        messageDiv.hide();
    }, 5000);
}

function showRegisterMessage(message, type) {
    const messageDiv = $('#register-message');
    messageDiv.text(message);
    messageDiv.removeClass('alert-danger alert-success').addClass('alert-' + type);
    messageDiv.show();
    
    // Hide the message after a few seconds
    setTimeout(function() {
        messageDiv.hide();
    }, 5000);
}

// Update these functions in app.js

function clearWordGame() {
    if (wordGameTimer) {
        clearInterval(wordGameTimer);
        wordGameTimer = null;
    }
    wordGameData = null;
    
    // Remove hint timer container and auto-hint notifications
    $('#hint-timer-container').remove();
    $('#auto-hint-notification').remove();
}

function initWordGame() {
    // Display word game data
    $('#word-game-score').text(wordGameData.score);
    $('#word-game-timer').text(wordGameData.timeLeft);
    $('#definition-text').text(wordGameData.definition);
    
    // Generate letter input boxes instead of placeholders
    generateLetterInputs();
    
    // Reset UI elements
    $('#hint-btn').hide(); // Hide hint button until first auto-hint
    $('#suggestion-box').hide();
    
    // Remove any existing hint timer containers first
    $('#hint-timer-container').remove();
    
    // Add hint timer display to the definition box
    $('.definition').append(
        `<div id="hint-timer-container" class="position-relative mt-2">
           <div class="alert alert-info py-2">
             Next auto-hint in <span id="hint-timer-display">${wordGameData.hintTimer}</span> seconds (-10 points)
           </div>
         </div>`
    );
    
    // Start the timer
    wordGameTimer = setInterval(updateWordGameTimer, 1000);
}

// Also ensure the startNewWordGame function properly clears the game before starting a new one
function startNewWordGame() {
    // Clear any existing game (this will clear timers and hint containers)
    clearWordGame();
    
    // Load a word game from the API
    $.ajax({
        url: 'api.php?path=jeu/word/en/60/10',
        method: 'GET',
        success: function(response) {
            wordGameData = {
                word: response.word,
                definition: response.definition,
                revealedLetters: new Array(response.word.length).fill(false),
                score: response.initialScore || response.word.length * 10,
                timeLeft: response.time || 60,
                hintInterval: response.hintInterval || 10,
                hintTimer: response.hintInterval || 10, // Countdown to next hint
                hintShown: false,
                hintButtonShown: false,
                suggestions: response.suggestions || []
            };
            
            initWordGame();
        },
        error: function(xhr) {
            console.error('Error starting word game:', xhr);
            
            // Fallback to mock data if API fails
            const mockWords = [
                { word: "ALGORITHM", definition: "A process or set of rules to be followed in calculations or problem-solving operations." },
                { word: "JAVASCRIPT", definition: "A high-level, interpreted programming language often used for web development." },
                { word: "DATABASE", definition: "An organized collection of data stored electronically in a computer system." },
                { word: "INTERFACE", definition: "A point where two systems meet and interact." },
                { word: "VARIABLE", definition: "A symbol that represents a value that may change in a program." }
            ];
            
            // Choose a random word
            const randomWord = mockWords[Math.floor(Math.random() * mockWords.length)];
            
            wordGameData = {
                word: randomWord.word,
                definition: randomWord.definition,
                revealedLetters: new Array(randomWord.word.length).fill(false),
                score: randomWord.word.length * 10,
                timeLeft: 60,
                hintInterval: 10,
                hintTimer: 10,
                hintShown: false,
                hintButtonShown: false,
                suggestions: []
            };
            
            initWordGame();
        }
    });
}

// New function to generate letter input boxes
function generateLetterInputs() {
    const wordLength = wordGameData.word.length;
    let inputsHTML = '<div class="d-flex justify-content-center letter-inputs">';
    
    for (let i = 0; i < wordLength; i++) {
        // Create an input for each letter
        inputsHTML += `
            <div class="letter-input-container mx-1">
                <input type="text" 
                       class="form-control letter-input text-center" 
                       maxlength="1" 
                       data-index="${i}" 
                       ${wordGameData.revealedLetters[i] ? 'value="' + wordGameData.word[i] + '" disabled' : ''}>
            </div>
        `;
    }
    
    inputsHTML += '</div>';
    $('#word-display').html(inputsHTML);
    
    // Add event listeners to the letter inputs
    $('.letter-input').on('input', function() {
        const index = $(this).data('index');
        const letter = $(this).val().trim().toUpperCase();
        
        if (letter) {
            checkLetterAtPosition(letter, index);
            
            // Move focus to next input if available
            if (index < wordLength - 1) {
                $('.letter-input').eq(index + 1).focus();
            }
        }
    });
}


function checkLetterAtPosition(letter, index) {
    if (!wordGameData) return;
    
    const correctLetter = wordGameData.word[index].toUpperCase();
    const input = $(`.letter-input[data-index="${index}"]`);
    
    if (letter === correctLetter) {
        // Correct letter - simply keep the letter and disable without green styling
        input.removeClass('is-invalid is-valid'); // Remove any validation classes
        input.prop('disabled', true);
        wordGameData.revealedLetters[index] = true;
        updateWordGameScore(5);
        
        // Add a subtle background color without using Bootstrap validation
        input.css('background-color', '#f8f9fa');
        input.css('border-color', '#ced4da');
        
        // Check if all letters are revealed
        checkGameCompletion();
    } else {
        // Wrong letter - flash red briefly
        input.addClass('is-invalid');
        updateWordGameScore(-5);
        
        // Clear the input and remove the red styling after a short delay
        setTimeout(() => {
            input.val('');
            input.removeClass('is-invalid');
            input.focus();
        }, 500);
    }
}


function updateWordGameTimer() {
    if (!wordGameData) return;
    
    // Update game time
    wordGameData.timeLeft--;
    $('#word-game-timer').text(wordGameData.timeLeft);
    
    // Update hint timer
    wordGameData.hintTimer--;
    
    // Update hint timer display if it exists
    if ($('#hint-timer-display').length > 0) {
        $('#hint-timer-display').text(wordGameData.hintTimer);
    }
    
    // Auto-reveal a letter when hint timer reaches zero
    if (wordGameData.hintTimer === 0) {
        // Reset the hint timer
        wordGameData.hintTimer = wordGameData.hintInterval;
        
        // Reveal a random letter and deduct points
        revealRandomLetter();
        updateWordGameScore(-10);
        
        // After the first auto-hint, show the hint button if not already shown
        if (!wordGameData.hintButtonShown) {
            $('#hint-btn').show();
            wordGameData.hintButtonShown = true;
        }
    }
    
    // End game when time runs out
    if (wordGameData.timeLeft <= 0) {
        clearInterval(wordGameTimer);
        wordGameTimer = null;
        
        // Reveal the word
        revealWord();
        
        // Show game over message
        setTimeout(function() {
            alert("Time's up! The word was: " + wordGameData.word);
        }, 300);
    }
}

// Updated function to reveal a letter with auto-reveal styling
function revealRandomLetter() {
    if (!wordGameData) return;
    
    // Find indices of hidden letters
    const hiddenIndices = [];
    for (let i = 0; i < wordGameData.word.length; i++) {
        if (!wordGameData.revealedLetters[i]) {
            hiddenIndices.push(i);
        }
    }
    
    // Reveal a random hidden letter
    if (hiddenIndices.length > 0) {
        const randomIndex = hiddenIndices[Math.floor(Math.random() * hiddenIndices.length)];
        wordGameData.revealedLetters[randomIndex] = true;
        
        // Update the input field with special styling for auto-revealed letters
        const input = $(`.letter-input[data-index="${randomIndex}"]`);
        input.val(wordGameData.word[randomIndex]);
        input.prop('disabled', true);
        input.removeClass('is-valid is-invalid').addClass('auto-revealed');
        
        // Display auto-hint notification
        showAutoHintNotification();
        
        // Check if all letters are revealed
        checkGameCompletion();
    }
}

// Function to show a notification when an auto-hint occurs
function showAutoHintNotification() {
    // Create notification if it doesn't exist
    if ($('#auto-hint-notification').length === 0) {
        $('<div id="auto-hint-notification" class="alert alert-warning position-fixed" style="top: 70px; right: 20px; z-index: 1050; display: none;">' +
          '<strong>Auto-Hint!</strong> A letter was revealed. -10 points.' +
          '</div>').appendTo('body');
    }
    
    // Show notification
    $('#auto-hint-notification').fadeIn().delay(2000).fadeOut();
}

function updateWordGameScore(points) {
    if (!wordGameData) return;
    
    wordGameData.score += points;
    $('#word-game-score').text(wordGameData.score);
}

// Function to check if all letters are revealed (game is complete)
function checkGameCompletion() {
    if (!wordGameData) return;
    
    // Check if all letters are revealed
    if (wordGameData.revealedLetters.every(revealed => revealed)) {
        // Player has won
        clearInterval(wordGameTimer);
        wordGameTimer = null;
        
        // Calculate bonus based on time left
        const timeBonus = Math.floor(wordGameData.timeLeft / 10);
        updateWordGameScore(timeBonus);
        
        // Update player score in database if logged in
        if (currentUser) {
            updatePlayerScore(wordGameData.score);
        }
        
        // Show win message
        setTimeout(function() {
            alert(`Congratulations! You've revealed the entire word and earned ${timeBonus} bonus points for the time left!`);
        }, 300);
    }
}

// Function to update player's score in the database
function updatePlayerScore(score) {
    if (!currentUser) return;
    
    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'update_score',
            username: currentUser.username,
            score: score
        }
    });
}

// Function to reveal the entire word when game is over
function revealWord() {
    if (!wordGameData) return;
    
    for (let i = 0; i < wordGameData.word.length; i++) {
        const input = $(`.letter-input[data-index="${i}"]`);
        input.val(wordGameData.word[i]);
        input.prop('disabled', true);
    }
    
    wordGameData.revealedLetters.fill(true);
}

function handleGuessWord() {
    if (!wordGameData) return;
    
    const guess = prompt("Enter your guess for the word:");
    if (!guess) return;
    
    if (guess.trim().toUpperCase() === wordGameData.word.toUpperCase()) {
        // Reveal all letters
        revealWord();
        
        // Calculate bonus based on time left
        const timeBonus = Math.floor(wordGameData.timeLeft / 10);
        updateWordGameScore(timeBonus * 2); // Double bonus for guessing the whole word
        
        // End the game
        clearInterval(wordGameTimer);
        wordGameTimer = null;
        
        // Update player score in database if logged in
        if (currentUser) {
            updatePlayerScore(wordGameData.score);
        }
        
        // Show win message
        setTimeout(function() {
            alert(`Correct! You win with ${wordGameData.score} points and a time bonus of ${timeBonus * 2}!`);
        }, 300);
    } else {
        // Wrong guess
        updateWordGameScore(-5);
        alert("Sorry, that's not the correct word.");
    }
}

function handleHintRequest() {
    if (!wordGameData) return;
    
    // Apply cost for hint (20 points)
    updateWordGameScore(-20);
    
    // Show suggestion box and hide hint button
    $('#hint-btn').hide();
    $('#suggestion-box').show();
    
    // Generate suggestions based on revealed letters
    generateSuggestions();
}

// Function to generate word suggestions based on revealed letters
function generateSuggestions() {
    // Create a pattern with the revealed letters
    let pattern = '';
    for (let i = 0; i < wordGameData.word.length; i++) {
        pattern += wordGameData.revealedLetters[i] ? wordGameData.word[i] : '.';
    }
    
    let suggestionHtml = '';
    
    // If we have suggestions from the API, use those
    if (wordGameData.suggestions && wordGameData.suggestions.length > 0) {
        wordGameData.suggestions.forEach(word => {
            suggestionHtml += `<a href="#" class="list-group-item list-group-item-action suggestion-item" data-word="${word}">${word}</a>`;
        });
    } else {
        // Fallback to just showing the actual word
        suggestionHtml = `<a href="#" class="list-group-item list-group-item-action suggestion-item" data-word="${wordGameData.word}">${wordGameData.word}</a>`;
    }
    
    $('#suggestion-list').html(suggestionHtml);
    
    // Add click handler for suggestion
    $('.suggestion-item').on('click', function(e) {
        e.preventDefault();
        const word = $(this).data('word');
        
        // Fill in the word
        for (let i = 0; i < word.length; i++) {
            const input = $(`.letter-input[data-index="${i}"]`);
            input.val(word[i]);
            input.prop('disabled', true);
            
            if (!wordGameData.revealedLetters[i]) {
                wordGameData.revealedLetters[i] = true;
            }
        }
        
        // Check game completion
        checkGameCompletion();
    });
}

// Definition Game Functions
function startNewDefGame() {
    // Clear any existing game
    clearDefGame();
    
    // Load a definition game from the API
    $.ajax({
        url: 'api.php?path=jeu/def/en/60',
        method: 'GET',
        success: function(response) {
            defGameData = {
                word: response.word,
                wordId: response.wordId,
                definitions: [],
                score: 0,
                timeLeft: response.time || 60
            };
            
            initDefGame();
        },
        error: function(xhr) {
            console.error('Error starting definition game:', xhr);
            
            // Fallback to mock data if API fails
            const mockWords = ["ALGORITHM", "JAVASCRIPT", "DATABASE", "INTERFACE", "VARIABLE"];
            const randomWord = mockWords[Math.floor(Math.random() * mockWords.length)];
            
            defGameData = {
                word: randomWord,
                wordId: Math.floor(Math.random() * 1000),
                definitions: [],
                score: 0,
                timeLeft: 60
            };
            
            initDefGame();
        }
    });
}

function clearDefGame() {
    if (defGameTimer) {
        clearInterval(defGameTimer);
        defGameTimer = null;
    }
    defGameData = null;
}

function initDefGame() {
    // Display definition game data
    $('#def-game-score').text(defGameData.score);
    $('#def-game-timer').text(defGameData.timeLeft);
    $('#def-word-display').text(defGameData.word);
    
    // Reset UI elements
    $('#definition-input').val('');
    $('#def-message').hide();
    $('#definitions-list').empty();
    
    // Start the timer
    defGameTimer = setInterval(updateDefGameTimer, 1000);
}

function updateDefGameTimer() {
    if (!defGameData) return;
    
    defGameData.timeLeft--;
    $('#def-game-timer').text(defGameData.timeLeft);
    
    // End game when time runs out
    if (defGameData.timeLeft <= 0) {
        clearInterval(defGameTimer);
        defGameTimer = null;
        
        // Disable input
        $('#definition-input').prop('disabled', true);
        $('#submit-def-btn').prop('disabled', true);
        
        // Update player score in database if logged in
        if (currentUser && defGameData.score > 0) {
            updatePlayerScore(defGameData.score);
        }
        
        // Show game over message
        showDefMessage(`Time's up! You created ${defGameData.definitions.length} definition(s) and earned ${defGameData.score} points.`, 'info');
    }
}

function updateDefGameScore(points) {
    if (!defGameData) return;
    
    defGameData.score += points;
    $('#def-game-score').text(defGameData.score);
}

function handleSubmitDefinition() {
    if (!defGameData) return;
    if (defGameData.timeLeft <= 0) return;
    
    const definition = $('#definition-input').val().trim();
    
    // Validate definition
    if (definition.length < 5) {
        showDefMessage('Definition must be at least 5 characters long.', 'danger');
        return;
    }
    
    if (definition.length > 200) {
        showDefMessage('Definition must be no more than 200 characters long.', 'danger');
        return;
    }
    
    if (defGameData.definitions.includes(definition)) {
        showDefMessage('You have already submitted this definition.', 'danger');
        return;
    }
    
    // Submit the definition to the server
    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'add_definition',
            word_id: defGameData.wordId,
            word: defGameData.word,
            definition: definition,
            language: 'en',
            user: currentUser ? currentUser.username : 'Guest'
        },
        success: function(response) {
            // Add definition to the list
            defGameData.definitions.push(definition);
            
            // Update score
            updateDefGameScore(5);
            
            // Add to UI
            $('#definitions-list').append(`
                <div class="list-group-item">
                    ${definition}
                </div>
            `);
            
            // Clear input
            $('#definition-input').val('');
            
            // Show success message
            showDefMessage('Definition added successfully! (+5 points)', 'success');
        },
        error: function(xhr) {
            // Handle error (duplicate definition, server error, etc.)
            console.error('Error submitting definition:', xhr);
            
            if (xhr.responseJSON && xhr.responseJSON.error) {
                showDefMessage(xhr.responseJSON.error, 'danger');
            } else {
                // For demo purposes, if API fails, still add the definition locally
                // Add definition to the list
                defGameData.definitions.push(definition);
                
                // Update score
                updateDefGameScore(5);
                
                // Add to UI
                $('#definitions-list').append(`
                    <div class="list-group-item">
                        ${definition}
                    </div>
                `);
                
                // Clear input
                $('#definition-input').val('');
                
                // Show success message
                showDefMessage('Definition added successfully! (+5 points)', 'success');
            }
        }
    });
}

function showDefMessage(message, type) {
    const messageDiv = $('#def-message');
    messageDiv.text(message);
    messageDiv.removeClass('alert-danger alert-success alert-info').addClass('alert-' + type);
    messageDiv.show();
    
    // Hide the message after a few seconds
    setTimeout(function() {
        messageDiv.hide();
    }, 5000);
}

// Definitions Table Functions
function initDefinitionsTable() {
    // Add loading indicator
    $('#view-defs-page').append('<div id="loading-indicator" class="text-center my-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading definitions...</p></div>');
    
    // Load definitions from the API
    $.ajax({
        url: 'api.php?path=dump/50', // Fetch 50 definitions
        method: 'GET',
        success: function(response) {
            $('#loading-indicator').remove();
            initTableWithData(response);
        },
        error: function(xhr) {
            $('#loading-indicator').remove();
            console.error('Error loading definitions:', xhr);
            
            // For demo, use mock data if API fails
            const mockDefinitions = [
                { id: 1, language: 'en', word: 'ALGORITHM', definition: 'A process or set of rules to be followed in calculations or problem-solving operations.' },
                { id: 2, language: 'en', word: 'JAVASCRIPT', definition: 'A high-level, interpreted programming language often used for web development.' },
                { id: 3, language: 'en', word: 'DATABASE', definition: 'An organized collection of data stored electronically in a computer system.' },
                { id: 4, language: 'en', word: 'INTERFACE', definition: 'A point where two systems meet and interact.' },
                { id: 5, language: 'en', word: 'VARIABLE', definition: 'A symbol that represents a value that may change in a program.' },
                { id: 6, language: 'fr', word: 'ORDINATEUR', definition: 'Machine électronique programmable qui traite les données.' },
                { id: 7, language: 'fr', word: 'PROGRAMMATION', definition: 'Action d\'établir un programme d\'opérations.' },
                { id: 8, language: 'fr', word: 'LOGICIEL', definition: 'Ensemble des programmes, procédés et règles relatifs au fonctionnement d\'un ensemble de traitement de données.' },
                { id: 9, language: 'fr', word: 'INTERNET', definition: 'Réseau informatique mondial accessible au public.' },
                { id: 10, language: 'fr', word: 'SERVEUR', definition: 'Système informatique destiné à fournir des services à des utilisateurs connectés.' },
                // Add more mock data to simulate larger dataset
                { id: 11, language: 'en', word: 'FUNCTION', definition: 'A relation that associates each element of a set with exactly one element of another set.' },
                { id: 12, language: 'en', word: 'ARRAY', definition: 'An ordered collection of values identified by index.' },
                { id: 13, language: 'en', word: 'OBJECT', definition: 'A collection of properties, and a property is an association between a name and a value.' },
                { id: 14, language: 'en', word: 'CLASS', definition: 'A template for creating objects, providing initial values and implementation of behavior.' },
                { id: 15, language: 'en', word: 'INHERITANCE', definition: 'The mechanism by which an object can acquire properties and methods of another object.' },
                { id: 16, language: 'fr', word: 'NAVIGATEUR', definition: 'Logiciel permettant d\'afficher les pages web et de naviguer sur internet.' },
                { id: 17, language: 'fr', word: 'SERVEUR', definition: 'Ordinateur ou logiciel qui rend service à un ou plusieurs clients.' },
                { id: 18, language: 'fr', word: 'RÉSEAU', definition: 'Ensemble d\'ordinateurs et de périphériques reliés entre eux.' },
                { id: 19, language: 'fr', word: 'TÉLÉCHARGEMENT', definition: 'Opération de transmission d\'informations d\'un ordinateur à un autre.' },
                { id: 20, language: 'fr', word: 'COURRIEL', definition: 'Message transmis par un réseau télématique d\'un utilisateur à un ou plusieurs destinataires.' }
            ];
            
            initTableWithData(mockDefinitions);
        }
    });
}

function initTableWithData(data) {
    // Add language filter UI
    $('#view-defs-page').prepend(`
        <div class="mb-4">
            <label class="me-2">Filter by language:</label>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary active" data-filter="all">All</button>
                <button type="button" class="btn btn-outline-primary" data-filter="en">English</button>
                <button type="button" class="btn btn-outline-primary" data-filter="fr">French</button>
            </div>
        </div>
    `);
    
    // Initialize DataTable with enhanced options
    const definitionsTable = $('#definitions-table').DataTable({
        data: data,
        columns: [
            { data: 'id' },
            { data: 'language' },
            { data: 'word' },
            { data: 'definition' }
        ],
        pageLength: 25,                      // Show 25 entries by default
        lengthMenu: [10, 25, 50, 100, -1],   // Options including "All"
        language: {
            lengthMenu: "Show _MENU_ definitions per page",
            info: "Showing _START_ to _END_ of _TOTAL_ definitions",
            search: "Search definitions:",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            },
            zeroRecords: "No matching definitions found",
            infoEmpty: "No definitions available",
            infoFiltered: "(filtered from _MAX_ total definitions)"
        },
        responsive: true,
        dom: '<"top"lf>rt<"bottom"ip><"clear">'
    });
    
    // Add language filter functionality
    $('.btn-group button').on('click', function() {
        const filter = $(this).data('filter');
        
        // Update active button
        $('.btn-group button').removeClass('active');
        $(this).addClass('active');
        
        // Apply filter
        if (filter === 'all') {
            definitionsTable.column(1).search('').draw();
        } else {
            definitionsTable.column(1).search(filter).draw();
        }
    });
    
    // Add load more button if needed
    if (data.length === 50) {  // If we got exactly 50 definitions, assume there might be more
        $('#view-defs-page').append(`
            <div class="text-center mt-4">
                <button id="load-more-defs" class="btn btn-outline-primary">Load More Definitions</button>
            </div>
        `);
        
        // Handle load more click
        $('#load-more-defs').on('click', function() {
            const offset = data.length;
            
            $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...');
            $(this).prop('disabled', true);
            
            // Fetch more definitions with an offset
            $.ajax({
                url: `api.php?path=dump/50/${offset}`,
                method: 'GET',
                success: function(moreData) {
                    // Add new data to the table
                    if (moreData && moreData.length > 0) {
                        moreData.forEach(function(item) {
                            definitionsTable.row.add(item).draw(false);
                        });
                        
                        // Update the button
                        $('#load-more-defs').html('Load More Definitions');
                        $('#load-more-defs').prop('disabled', false);
                        
                        // If fewer than 50 definitions were returned, assume we've reached the end
                        if (moreData.length < 50) {
                            $('#load-more-defs').remove();
                        }
                    } else {
                        // No more data
                        $('#load-more-defs').remove();
                    }
                },
                error: function(xhr) {
                    console.error('Error loading more definitions:', xhr);
                    $('#load-more-defs').html('Load More Definitions');
                    $('#load-more-defs').prop('disabled', false);
                }
            });
        });
    }
}

// Leaderboard Functions
function loadLeaderboard() {
    // Load leaderboard data from the API
    $.ajax({
        url: 'api.php?path=admin/top/5',
        method: 'GET',
        success: function(response) {
            displayLeaderboard(response);
        },
        error: function(xhr) {
            console.error('Error loading leaderboard:', xhr);
            
            // For demo, use mock data if API fails
            const mockLeaderboard = [
                { login: 'player1', score: 850 },
                { login: 'player2', score: 720 },
                { login: 'player3', score: 635 },
                { login: 'player4', score: 520 },
                { login: 'player5', score: 485 }
            ];
            
            displayLeaderboard(mockLeaderboard);
        }
    });
}

function displayLeaderboard(data) {
    const tbody = $('#leaderboard-body');
    tbody.empty();
    
    data.forEach((player, index) => {
        tbody.append(`
            <tr>
                <th scope="row">${index + 1}</th>
                <td>${player.login}</td>
                <td>${player.score}</td>
            </tr>
        `);
    });
}