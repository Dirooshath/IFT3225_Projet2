let currentUser = null;
let isAdminUser = false;
let wordGameTimer = null;
let defGameTimer = null;
let wordGameData = null;
let defGameData = null;
let usersTable = null;
let definitionsAdminTable = null;

// Router configuration
const routes = {
    'home': showHomePage,
    'login': showLoginPage,
    'register': showRegisterPage,
    'play-word': showWordGamePage,
    'create-def': showDefGamePage,
    'view-defs': showDefsPage,
    'admin': showAdminDashboard
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
    setupAdminEventHandlers();
    
    // Load leaderboard data
    loadLeaderboard();
});

// Check if user is already logged in (simulated here)
function checkUserSession() {
    const storedUser = localStorage.getItem('currentUser');
    if (storedUser) {
        currentUser = JSON.parse(storedUser);
        isAdminUser = currentUser.isAdmin || false;
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

// Admin Dashboard Function
function showAdminDashboard() {
    // Show admin dashboard page
    $('#admin-dashboard-page').addClass('active-page');
    
    // Redirect non-admin users
    if (!currentUser || !currentUser.isAdmin) {
        window.location.hash = 'home';
        return;
    }
    
    // Load admin data
    loadAdminData();
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
                score: response.score || 0,
                isAdmin: response.is_admin || false
            };
            
            // Store admin status
            isAdminUser = response.is_admin || false;
            
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
            isAdminUser = false;
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
            isAdminUser = false;
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
    
    // Add admin menu item if user is admin
    if (currentUser.isAdmin) {
        // Check if admin nav item already exists
        if ($('#admin-nav-item').length === 0) {
            $('#navbarNav .navbar-nav').append(`
                <li class="nav-item" id="admin-nav-item">
                    <a class="nav-link" href="#admin">Admin Dashboard</a>
                </li>
            `);
        } else {
            $('#admin-nav-item').show();
        }
    } else {
        $('#admin-nav-item').hide();
    }
}

function updateUIForLoggedOutUser() {
    // Update navigation
    $('#user-display').hide();
    $('#login-nav-btn, #register-nav-btn').show();
    $('#logout-btn').hide();
    
    // Update game player names
    $('#word-game-player, #def-game-player').text('Guest');
    
    // Hide admin nav item
    $('#admin-nav-item').hide();
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

// Word Game functions
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

function startNewWordGame() {
    // Clear any existing game (this will clear timers and hint containers)
    clearWordGame();
    
    // Load a word game from the API
    $.ajax({
        url: 'api.php?path=jeu/word/en/60/10',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            // If there's an error in the response
            if (response.error) {
                console.error('API Error:', response.error);
                alert('Error getting word game: ' + response.error);
                useMockData();
                return;
            }
            
            const word = response.word;
            
            // If we didn't get good suggestions from the server, generate our own
            let suggestions = response.suggestions || [];
            if (!suggestions.length || suggestions.length < 10) {
                suggestions = generateSmartSuggestions(word);
            }
            
            wordGameData = {
                word: word,
                definition: response.definition,
                revealedLetters: new Array(word.length).fill(false),
                score: response.initialScore || word.length * 10,
                timeLeft: response.time || 60,
                hintInterval: response.hintInterval || 10,
                hintTimer: response.hintInterval || 10,
                hintShown: false,
                hintButtonShown: false,
                suggestions: suggestions
            };
            
            initWordGame();
        },
        error: function(xhr, status, error) {
            console.error('Error starting word game:', xhr.responseText);
            
            let errorMessage = 'Failed to connect to the game server.';
            
            try {
                const responseJson = JSON.parse(xhr.responseText);
                if (responseJson.error) {
                    errorMessage = responseJson.error;
                }
            } catch (e) {
                console.error('Response is not JSON:', xhr.responseText);
                console.error('Parse error:', e);
            }
            
            // Always use mock data when there's an error
            console.log('Using mock data for word game due to error');
            useMockData();
        }
    });
}

function useMockData() {
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

function updatePlayerScore(score) {
    if (!currentUser) return;
    
    $.ajax({
        url: 'api.php',
        method: 'POST',
        data: {
            action: 'update_score',
            username: currentUser.username,
            score: score
        },
        success: function(response) {
            console.log('Score updated successfully:', response);
            // Refresh leaderboard after score update
            loadLeaderboard();
        },
        error: function(xhr) {
            console.error('Error updating score:', xhr);
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

function generateSuggestions() {
    if (!wordGameData) return;
    
    // Create a pattern with the revealed letters
    let pattern = '';
    for (let i = 0; i < wordGameData.word.length; i++) {
        pattern += wordGameData.revealedLetters[i] ? wordGameData.word[i].toUpperCase() : '.';
    }
    
    console.log('Current pattern:', pattern);
    
    let suggestionHtml = '';
    let matchingSuggestions = [];
    
    // If we have suggestions from the API, filter them based on the current pattern
    if (wordGameData.suggestions && wordGameData.suggestions.length > 0) {
        // Create a regex pattern to match words with revealed letters in correct positions
        const regexPattern = new RegExp('^' + pattern + '$', 'i');
        
        // Filter suggestions that match the pattern
        matchingSuggestions = wordGameData.suggestions.filter(word => {
            return regexPattern.test(word);
        });
        
        console.log('Filtered suggestions that match pattern:', matchingSuggestions);
        
        // If no matching suggestions were found, provide a fallback mechanism
        if (matchingSuggestions.length === 0) {
            // Fallback 1: Find words that match at least the positions that have been revealed
            matchingSuggestions = wordGameData.suggestions.filter(word => {
                word = word.toUpperCase();
                for (let i = 0; i < wordGameData.word.length; i++) {
                    if (wordGameData.revealedLetters[i] && word[i] !== wordGameData.word[i].toUpperCase()) {
                        return false;
                    }
                }
                return true;
            });
            
            console.log('Fallback 1 - Match revealed positions:', matchingSuggestions);
            
            // Fallback 2: If still no matches, include all suggestions as a last resort
            if (matchingSuggestions.length === 0) {
                matchingSuggestions = wordGameData.suggestions;
                console.log('Fallback 2 - Using all suggestions:', matchingSuggestions);
            }
        }
        
        // Always include the correct word (hidden among other suggestions)
        if (!matchingSuggestions.some(word => word.toUpperCase() === wordGameData.word.toUpperCase())) {
            matchingSuggestions.push(wordGameData.word);
            
            // Shuffle the array to ensure the correct word isn't always at the end
            matchingSuggestions = shuffleArray(matchingSuggestions);
        }
        
        // Generate HTML for matching suggestions
        matchingSuggestions.forEach(word => {
            suggestionHtml += `<a href="#" class="list-group-item list-group-item-action suggestion-item" data-word="${word}">${word}</a>`;
        });
    } else {
        // If we don't have any suggestions at all, just show a few random words including the correct one
        const mockSuggestions = generateMockSuggestions(wordGameData.word, 5);
        mockSuggestions.forEach(word => {
            suggestionHtml += `<a href="#" class="list-group-item list-group-item-action suggestion-item" data-word="${word}">${word}</a>`;
        });
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

// Helper function to shuffle an array (Fisher-Yates shuffle)
function shuffleArray(array) {
    const newArray = [...array]; // Create a copy to avoid modifying the original
    for (let i = newArray.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [newArray[i], newArray[j]] = [newArray[j], newArray[i]];
    }
    return newArray;
}

// Helper function to generate mock suggestions when API doesn't provide any
function generateMockSuggestions(word, count) {
    const wordLength = word.length;
    const suggestions = [word];
    
    // Create a few random variations of the word
    for (let i = 0; i < count; i++) {
        let variation = '';
        for (let j = 0; j < wordLength; j++) {
            // 50% chance to keep the same letter, otherwise random letter
            if (Math.random() > 0.5) {
                variation += word[j];
            } else {
                const randomChar = String.fromCharCode(65 + Math.floor(Math.random() * 26));
                variation += randomChar;
            }
        }
        suggestions.push(variation);
    }
    
    return shuffleArray(suggestions);
}

// Define generateSmartSuggestions function (placeholder)
function generateSmartSuggestions(word) {
    // This would ideally use some smart algorithm to generate plausible words
    // For now, just return some mock suggestions
    return generateMockSuggestions(word, 10);
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
            
            // Update currentUser score in memory
            if (currentUser) {
                currentUser.score += 5;
                localStorage.setItem('currentUser', JSON.stringify(currentUser));
            }
            
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
            
            // Refresh leaderboard data in the background
            loadLeaderboard();
        }
        ,
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

// Replace the initDefinitionsTable function with this simplified version
// Replace your current initDefinitionsTable function with this enhanced version

function initDefinitionsTable() {
    // Clear any existing DataTable
    if ($.fn.DataTable.isDataTable('#definitions-table')) {
        $('#definitions-table').DataTable().destroy();
    }
    
    // Add custom search and filter UI above the table
    $('#view-defs-page h2').after(`
        <div class="mb-4">
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="definition-search" class="form-control" 
                               placeholder="Search words or definitions...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="btn-group float-md-end" id="language-filter" role="group">
                        <button type="button" class="btn btn-outline-primary active" data-filter="">All Languages</button>
                        <button type="button" class="btn btn-outline-primary" data-filter="en">English</button>
                        <button type="button" class="btn btn-outline-primary" data-filter="fr">French</button>
                    </div>
                </div>
            </div>
        </div>
    `);
    
    // Variables to track current search state
    let currentSearchTerm = '';
    let currentLanguage = '';
    let currentSortColumn = 0;  // Default sort column (ID)
    let currentSortDir = 'asc'; // Default sort direction
    let searchTimeout = null;
    
    // Initialize the DataTable with server-side processing
    let definitionsTable = $('#definitions-table').DataTable({
        processing: true,
        serverSide: true,
        searching: false, // Disable built-in search as we use our own
        lengthChange: false, // Hide page length options
        pageLength: 50, // Show 50 entries per page
        ordering: true, // Enable ordering/sorting
        order: [[currentSortColumn, currentSortDir]], // Initial sort
        ajax: {
            url: 'api.php',
            data: function(data) {
                // Add our custom parameters to the DataTables request
                data.path = 'dump/50/0';
                data.term = currentSearchTerm;
                data.lang = currentLanguage;
                
                // Add sorting information
                data.sortColumn = data.order[0].column;
                data.sortDir = data.order[0].dir;
                
                // Save current sort state
                currentSortColumn = data.order[0].column;
                currentSortDir = data.order[0].dir;
                
                return data;
            }
        },
        columns: [
            { data: 'id' },
            { data: 'language' },
            { data: 'word', render: function(data) {
                return '<strong>' + data + '</strong>';
            }},
            { data: 'definition' }
        ]
    });
    
    // Add search event handler with debounce
    $('#definition-search').on('input', function() {
        const searchTerm = $(this).val().trim();
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Set a timeout to avoid too many requests
        searchTimeout = setTimeout(function() {
            currentSearchTerm = searchTerm;
            definitionsTable.ajax.reload();
        }, 300); // 300ms delay
    });
    
    // Add language filter handler
    $('#language-filter button').on('click', function() {
        $('#language-filter button').removeClass('active');
        $(this).addClass('active');
        
        currentLanguage = $(this).data('filter');
        definitionsTable.ajax.reload();
    });
}

// Admin Dashboard Functions
function loadAdminData() {
    loadUsersTable();
    loadDefinitionsAdminTable();
    loadGameStats();
}

// Function to load users table
function loadUsersTable() {
    $.ajax({
        url: 'api.php?path=admin/users',
        method: 'GET',
        success: function(users) {
            // Destroy existing DataTable if it exists
            if (usersTable) {
                usersTable.destroy();
            }
            
            // Clear table body
            $('#users-table tbody').empty();
            
            // Add rows to table
            $.each(users, function(i, user) {
                const lastLogin = new Date(user.derniere_connexion).toLocaleString();
                
                $('#users-table tbody').append(`
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.login}</td>
                        <td>${user.parties_jouees}</td>
                        <td>${user.parties_gagnees}</td>
                        <td>${user.score}</td>
                        <td>${lastLogin}</td>
                        <td>${user.is_admin == 1 ? '<span class="badge bg-primary">Yes</span>' : 'No'}</td>
                        <td>
                            <button class="btn btn-sm btn-primary edit-user-btn" data-id="${user.id}">Edit</button>
                            <button class="btn btn-sm btn-danger delete-user-btn" data-id="${user.id}" data-username="${user.login}">Delete</button>
                        </td>
                    </tr>
                `);
            });
            
            // Initialize DataTable
            usersTable = $('#users-table').DataTable({
                pageLength: 10,
                order: [[4, 'desc']], // Sort by score by default
                language: {
                    search: "Search users:"
                }
            });
            
            // Add event listeners for edit and delete buttons
            $('#users-table').on('click', '.edit-user-btn', function() {
                const userId = $(this).data('id');
                const row = $(this).closest('tr');
                
                // Fill modal with user data
                $('#edit-user-id').val(userId);
                $('#edit-username').val(row.find('td:eq(1)').text());
                $('#edit-games-played').val(row.find('td:eq(2)').text());
                $('#edit-games-won').val(row.find('td:eq(3)').text());
                $('#edit-score').val(row.find('td:eq(4)').text());
                $('#edit-is-admin').prop('checked', row.find('td:eq(6)').text().includes('Yes'));
                
                // Show modal
                $('#editUserModal').modal('show');
            });
            
            $('#users-table').on('click', '.delete-user-btn', function() {
                const userId = $(this).data('id');
                const username = $(this).data('username');
                
                // Set delete modal data
                $('#delete-username').text(username);
                
                // Set data attribute on confirm button
                $('#confirm-delete-user-btn').data('id', userId);
                
                // Show modal
                $('#deleteUserModal').modal('show');
            });
        },
        error: function(xhr) {
            console.error('Error loading users:', xhr);
            $('#users-table tbody').html(`
                <tr>
                    <td colspan="8" class="text-center text-danger">
                        Failed to load users. Please try again.
                    </td>
                </tr>
            `);
        }
    });
}

// Function to load definitions admin table
function loadDefinitionsAdminTable() {
    // Enhanced version with source column and actions
    $.ajax({
        url: 'api.php?path=dump/50',
        method: 'GET',
        success: function(response) {
            // Destroy existing DataTable if it exists
            if (definitionsAdminTable) {
                definitionsAdminTable.destroy();
            }
            
            // Clear table body
            $('#definitions-admin-table tbody').empty();
            
            // Add rows to table
            $.each(response.data, function(i, def) {
                $('#definitions-admin-table tbody').append(`
                    <tr>
                        <td>${def.id}</td>
                        <td>${def.language}</td>
                        <td>${def.word}</td>
                        <td>${def.definition}</td>
                        <td>${def.source || 'system'}</td>
                        <td>
                            <button class="btn btn-sm btn-primary edit-def-btn" data-id="${def.id}">Edit</button>
                            <button class="btn btn-sm btn-danger delete-def-btn" data-id="${def.id}" data-word="${def.word}">Delete</button>
                        </td>
                    </tr>
                `);
            });
            
            // Initialize DataTable
            definitionsAdminTable = $('#definitions-admin-table').DataTable({
                pageLength: 10,
                order: [[0, 'desc']], // Sort by ID by default
                language: {
                    search: "Search definitions:"
                }
            });
            
            // Add event listeners for edit and delete buttons
            $('#definitions-admin-table').on('click', '.edit-def-btn', function() {
                const defId = $(this).data('id');
                const row = $(this).closest('tr');
                
                // Fill modal with definition data
                $('#edit-definition-id').val(defId);
                $('#edit-word').val(row.find('td:eq(2)').text());
                $('#edit-definition-text').val(row.find('td:eq(3)').text());
                $('#edit-language').val(row.find('td:eq(1)').text());
                $('#edit-source').val(row.find('td:eq(4)').text());
                
                // Show modal
                $('#editDefinitionModal').modal('show');
            });
            
            $('#definitions-admin-table').on('click', '.delete-def-btn', function() {
                const defId = $(this).data('id');
                const word = $(this).data('word');
                
                // Set delete modal data
                $('#delete-word').text(word);
                
                // Set data attribute on confirm button
                $('#confirm-delete-definition-btn').data('id', defId);
                
                // Show modal
                $('#deleteDefinitionModal').modal('show');
            });
        },
        error: function(xhr) {
            console.error('Error loading definitions:', xhr);
            $('#definitions-admin-table tbody').html(`
                <tr>
                    <td colspan="6" class="text-center text-danger">
                        Failed to load definitions. Please try again.
                    </td>
                </tr>
            `);
        }
    });
}

// Function to load game statistics
function loadGameStats() {
    $.ajax({
        url: 'api.php?path=admin/stats',
        method: 'GET',
        success: function(stats) {
            // Update stats
            $('#total-players').text(stats.totalPlayers);
            $('#total-games').text(stats.totalGames);
            $('#total-definitions').text(stats.totalDefinitions);
            $('#user-definitions').text(stats.userDefinitions);
            
            // Update active users list
            $('#active-users-list').empty();
            $.each(stats.activeUsers, function(i, user) {
                const lastActive = new Date(user.derniere_connexion).toLocaleString();
                $('#active-users-list').append(`
                    <tr>
                        <td>${user.login}</td>
                        <td>${user.parties_jouees}</td>
                        <td>${lastActive}</td>
                    </tr>
                `);
            });
            
            // Update popular words list
            $('#popular-words-list').empty();
            $.each(stats.popularWords, function(i, word) {
                $('#popular-words-list').append(`
                    <tr>
                        <td>${word.word}</td>
                        <td>${word.language}</td>
                        <td>${word.count}</td>
                    </tr>
                `);
            });
            
            // Update admin leaderboard
            $('#admin-leaderboard').empty();
            $.ajax({
                url: 'api.php?path=admin/top/10',
                method: 'GET',
                success: function(topUsers) {
                    $.each(topUsers, function(i, user) {
                        $('#admin-leaderboard').append(`
                            <tr>
                                <td>${i + 1}</td>
                                <td>${user.login}</td>
                                <td>${user.score}</td>
                            </tr>
                        `);
                    });
                }
            });
        },
        error: function(xhr) {
            console.error('Error loading game stats:', xhr);
            $('.tab-pane#stats-tab-pane .card-body').each(function() {
                $(this).html(`
                    <div class="alert alert-danger">
                        Failed to load statistics. Please try again.
                    </div>
                `);
            });
        }
    });
}

// Set up admin event handlers
function setupAdminEventHandlers() {
    // Users tab
    $('#refresh-users-btn').on('click', loadUsersTable);
    
    // Save user changes
    $('#save-user-btn').on('click', function() {
        const userId = $('#edit-user-id').val();
        const gamesPlayed = $('#edit-games-played').val();
        const gamesWon = $('#edit-games-won').val();
        const score = $('#edit-score').val();
        const isAdmin = $('#edit-is-admin').is(':checked') ? 1 : 0;
        
        $.ajax({
            url: 'api.php?path=admin/users/' + userId,
            method: 'POST',
            data: {
                parties_jouees: gamesPlayed,
                parties_gagnees: gamesWon,
                score: score,
                is_admin: isAdmin
            },
            success: function(response) {
                $('#editUserModal').modal('hide');
                loadUsersTable(); // Reload table
                
                // Show success alert
                showAdminAlert('User updated successfully!', 'success');
            },
            error: function(xhr) {
                console.error('Error updating user:', xhr);
                showAdminAlert('Failed to update user. Please try again.', 'danger');
            }
        });
    });
    
    // Confirm delete user
    $('#confirm-delete-user-btn').on('click', function() {
        const username = $('#delete-username').text();
        
        $.ajax({
            url: 'api.php?path=admin/delete/joueur/' + username,
            method: 'GET',
            success: function(response) {
                $('#deleteUserModal').modal('hide');
                loadUsersTable(); // Reload table
                
                // Show success alert
                showAdminAlert('User deleted successfully!', 'success');
            },
            error: function(xhr) {
                console.error('Error deleting user:', xhr);
                showAdminAlert('Failed to delete user. Please try again.', 'danger');
            }
        });
    });
    
    // Definitions tab
    $('#refresh-definitions-btn').on('click', loadDefinitionsAdminTable);
    
    // Add definition button
    $('#add-definition-btn').on('click', function() {
        // Clear form
        $('#add-definition-form')[0].reset();
        $('#add-source').val('admin'); // Default source
        
        // Show modal
        $('#addDefinitionModal').modal('show');
    });
    
    // Create definition
    $('#create-definition-btn').on('click', function() {
        const word = $('#add-word').val();
        const definition = $('#add-definition-text').val();
        const language = $('#add-language').val();
        const source = $('#add-source').val();
        
        if (!word || !definition) {
            showAdminAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        $.ajax({
            url: 'api.php',
            method: 'POST',
            data: {
                action: 'add_definition',
                word: word,
                definition: definition,
                language: language,
                user: source || 'admin'
            },
            success: function(response) {
                $('#addDefinitionModal').modal('hide');
                loadDefinitionsAdminTable(); // Reload table
                
                // Show success alert
                showAdminAlert('Definition added successfully!', 'success');
            },
            error: function(xhr) {
                console.error('Error adding definition:', xhr);
                showAdminAlert('Failed to add definition. Please try again.', 'danger');
            }
        });
    });
    
    // Save definition changes
    $('#save-definition-btn').on('click', function() {
        const defId = $('#edit-definition-id').val();
        const word = $('#edit-word').val();
        const definition = $('#edit-definition-text').val();
        const language = $('#edit-language').val();
        const source = $('#edit-source').val();
        
        $.ajax({
            url: 'api.php?path=admin/definitions/' + defId,
            method: 'POST',
            data: {
                word: word,
                definition: definition,
                language: language,
                source: source
            },
            success: function(response) {
                $('#editDefinitionModal').modal('hide');
                loadDefinitionsAdminTable(); // Reload table
                
                // Show success alert
                showAdminAlert('Definition updated successfully!', 'success');
            },
            error: function(xhr) {
                console.error('Error updating definition:', xhr);
                showAdminAlert('Failed to update definition. Please try again.', 'danger');
            }
        });
    });
    
    // Confirm delete definition
    $('#confirm-delete-definition-btn').on('click', function() {
        const defId = $(this).data('id');
        
        $.ajax({
            url: 'api.php?path=admin/delete/def/' + defId,
            method: 'GET',
            success: function(response) {
                $('#deleteDefinitionModal').modal('hide');
                loadDefinitionsAdminTable(); // Reload table
                
                // Show success alert
                showAdminAlert('Definition deleted successfully!', 'success');
            },
            error: function(xhr) {
                console.error('Error deleting definition:', xhr);
                showAdminAlert('Failed to delete definition. Please try again.', 'danger');
            }
        });
    });
    
    // Admin tabs switched
    $('#adminTabs button').on('click', function(e) {
        const targetTab = $(this).attr('id');
        
        // Refresh data when switching to tab
        if (targetTab === 'users-tab') {
            loadUsersTable();
        } else if (targetTab === 'definitions-tab') {
            loadDefinitionsAdminTable();
        } else if (targetTab === 'stats-tab') {
            loadGameStats();
        }
    });
}

// Utility function to show admin alerts
function showAdminAlert(message, type) {
    // Remove any existing alerts
    $('.admin-alert').remove();
    
    // Create alert
    const alert = $(`
        <div class="admin-alert alert alert-${type} alert-dismissible fade show">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);
    
    // Add to page
    $('#admin-dashboard-page').prepend(alert);
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        alert.alert('close');
    }, 5000);
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
    
    if (!data || data.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="3" class="text-center">No players found yet. Start playing to get on the leaderboard!</td>
            </tr>
        `);
        return;
    }
    
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