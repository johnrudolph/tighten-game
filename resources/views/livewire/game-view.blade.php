<script>


function gameData() {

    return {
        // Grid size
        gridWidth: 10,
        gridHeight: 10,
        grid: [],

        // Game entities
        dog: { x: 1, y: 8, direction: 2 }, // direction: 0=north, 1=east, 2=south, 3=west (start facing down)
        cows: [],

        // Game state
        cowsRemaining: 25,
        cowsScored: 0,
        cowsLost: 0,

        // Card system - movement and special cards
        cardTypes: [
            { name: 'Move 1', icon: '1️⃣', action: 'move1', weight: 3 },
            { name: 'Move 2', icon: '2️⃣', action: 'move2', weight: 3 },
            { name: 'Move 3', icon: '3️⃣', action: 'move3', weight: 2 },
            { name: 'Fence', icon: '🚧', action: 'fence', weight: 2 },
            { name: 'Bark', icon: '📢', action: 'bark', weight: 2 }
        ],
        hand: [],
        selectedSequence: [],

        // Fence system
        fences: [],

        // Animation state
        isPlaying: false,
        currentCardIndex: -1,

        initGame() {

            this.initGrid();
            this.initFences();
            this.initCows();
            this.dealNewHand();

        },

        initGrid() {
            this.grid = [];
            for (let y = 0; y < this.gridHeight; y++) {
                this.grid[y] = [];
                for (let x = 0; x < this.gridWidth; x++) {
                    this.grid[y][x] = null;
                }
            }
        },

        initFences() {
            // Add fences to create a funnel leading INTO the pen (pen is at 8,8 to 9,9)
            const penBorderFences = [
                // Left side fences (row 9, just outside the pen entrance)
                { x: 6, y: 9 }, { x: 7, y: 9 },
                // Right side fences (column 9, rows 6 and 7)
                { x: 9, y: 6 }, { x: 9, y: 7 }
            ];

            this.fences = [...penBorderFences];
        },

        initCows() {
            this.cows = [];
            for (let i = 0; i < 25; i++) {
                let x, y;
                do {
                    // Keep cows 2 spaces inside border
                    x = Math.floor(Math.random() * (this.gridWidth - 4)) + 2;
                    y = Math.floor(Math.random() * (this.gridHeight - 4)) + 2;
                } while (
                    (x === this.dog.x && y === this.dog.y) ||
                    this.isPen(x, y) ||
                    this.hasCowAt(x, y)
                );

                const colors = ['red', 'green', 'yellow'];
                const color = colors[Math.floor(Math.random() * colors.length)];
                this.cows.push({ id: i, x, y, color });
            }
        },

        dealNewHand() {
            this.hand = [];

            // Create weighted card pool
            const weightedCards = [];
            this.cardTypes.forEach(cardType => {
                for (let i = 0; i < cardType.weight; i++) {
                    weightedCards.push(cardType);
                }
            });

            for (let i = 0; i < 7; i++) {
                const cardType = weightedCards[Math.floor(Math.random() * weightedCards.length)];
                this.hand.push({ ...cardType, used: false });
            }
        },

        isPen(x, y) {
            return x >= 8 && x <= 9 && y >= 8 && y <= 9;
        },

        hasFenceAt(x, y) {
            return this.fences.some(fence => fence.x === x && fence.y === y);
        },

        hasCowAt(x, y) {
            return this.cows.some(cow => cow.x === x && cow.y === y);
        },

        getDogIcon() {
            const directions = ['dog-up', 'dog-right', 'dog-down', 'dog-left'];

            return directions[this.dog.direction];
        },

        selectCard(index) {
            if (this.hand[index].used || this.isPlaying) return;

            this.selectedSequence.push(this.hand[index]);
            this.hand[index].used = true;
        },

        addPivotToSequence(action) {
            if (this.isPlaying) return;

            const pivotActions = {
                'turnLeft': { name: 'Turn Left', icon: '↶', action: 'turnLeft' },
                'turnRight': { name: 'Turn Right', icon: '↷', action: 'turnRight' },
                'turnAround': { name: 'Turn Around', icon: '↺', action: 'turnAround' }
            };

            this.selectedSequence.push(pivotActions[action]);
        },

        removeFromSequence(index) {
            if (this.isPlaying) return;

            const removedCard = this.selectedSequence[index];
            this.selectedSequence.splice(index, 1);

            // Only mark hand cards as unused if it's a card from hand (not a pivot)
            if (removedCard.action && ['move1', 'move2', 'move3', 'fence', 'bark'].includes(removedCard.action)) {
                const handIndex = this.hand.findIndex(card =>
                    card.name === removedCard.name && card.used
                );
                if (handIndex !== -1) {
                    this.hand[handIndex].used = false;
                }
            }
        },

        clearSequence() {
            if (this.isPlaying) return;

            // Only reset movement cards, pivots don't need to be reset
            this.hand.forEach(card => card.used = false);
            this.selectedSequence = [];
        },

        async playSequence() {
            if (this.selectedSequence.length === 0 || this.isPlaying) return;

            this.isPlaying = true;

            for (let i = 0; i < this.selectedSequence.length; i++) {
                this.currentCardIndex = i;
                await this.executeCard(this.selectedSequence[i]);
                await this.sleep(800);
            }

            // After dog moves, handle cow reactions and random moves
            await this.handleCowReactions();

            this.isPlaying = false;
            this.currentCardIndex = -1;
            this.selectedSequence = [];
            this.dealNewHand();
        },

        async executeCard(card) {

            switch (card.action) {
                case 'move1':
                    await this.moveDogForward(1);
                    break;
                case 'move2':
                    await this.moveDogForward(2);
                    break;
                case 'move3':
                    await this.moveDogForward(3);
                    break;
                case 'fence':
                    await this.placeFence();
                    break;
                case 'bark':
                    await this.executeBark();
                    break;
                case 'turnLeft':
                    this.dog.direction = (this.dog.direction + 3) % 4;

                    // Force reactivity
                    this.dog = { ...this.dog };
                    await this.sleep(200);
                    break;
                case 'turnRight':
                    this.dog.direction = (this.dog.direction + 1) % 4;

                    // Force reactivity
                    this.dog = { ...this.dog };
                    await this.sleep(200);
                    break;
                case 'turnAround':
                    this.dog.direction = (this.dog.direction + 2) % 4;

                    // Force reactivity
                    this.dog = { ...this.dog };
                    await this.sleep(200);
                    break;
            }
        },

        async placeFence() {
            // Place fence in front of dog
            const directions = [
                { x: 0, y: 1 },     // direction 0 (north/up)
                { x: 1, y: 0 },     // direction 1 (east/right)
                { x: 0, y: -1 },    // direction 2 (south/down)
                { x: -1, y: 0 }     // direction 3 (west/left)
            ];

            const dir = directions[this.dog.direction];
            const fenceX = this.dog.x + dir.x;
            const fenceY = this.dog.y + dir.y;

            // Only place fence if space is valid and not already fenced
            if (fenceX >= 0 && fenceX < this.gridWidth &&
                fenceY >= 0 && fenceY < this.gridHeight &&
                !this.hasFenceAt(fenceX, fenceY) &&
                !this.isPen(fenceX, fenceY)) {

                this.fences.push({ x: fenceX, y: fenceY });

                // Move any cow currently in that space
                const cowInSpace = this.cows.find(cow => cow.x === fenceX && cow.y === fenceY);
                if (cowInSpace) {
                    await this.moveCowAwayFromDog(cowInSpace);
                }
            }

            await this.sleep(300);
        },

        async executeBark() {
            // Find all cows within 3 spaces (including diagonally)
            const affectedCows = this.cows.filter(cow => {
                const dx = Math.abs(cow.x - this.dog.x);
                const dy = Math.abs(cow.y - this.dog.y);
                const distance = Math.max(dx, dy); // Chebyshev distance (includes diagonals)
                return distance <= 3;
            });

            // Move each affected cow away from dog
            for (const cow of affectedCows) {
                await this.moveCowAwayFromDog(cow);
            }

            await this.sleep(300);
        },

        async moveDogForward(spaces) {
            const directions = [
                { x: 0, y: 1, name: 'UP (increasing Y)' },     // direction 0
                { x: 1, y: 0, name: 'RIGHT (increasing X)' },  // direction 1
                { x: 0, y: -1, name: 'DOWN (decreasing Y)' },  // direction 2
                { x: -1, y: 0, name: 'LEFT (decreasing X)' }   // direction 3
            ];

            const dir = directions[this.dog.direction];


            for (let i = 0; i < spaces; i++) {
                const newX = this.dog.x + dir.x;
                const newY = this.dog.y + dir.y;

                // Keep dog within bounds
                if (newX >= 0 && newX < this.gridWidth && newY >= 0 && newY < this.gridHeight) {
                    // Animate movement
                    this.dog = { ...this.dog, x: newX, y: newY };

                    // Check for immediate cow reaction (cow in same space)
                    const cowAtDogPosition = this.cows.find(cow => cow.x === this.dog.x && cow.y === this.dog.y);
                    if (cowAtDogPosition) {
                        await this.moveCowAwayFromDog(cowAtDogPosition);
                    }

                    // Check for orthogonally adjacent cow reactions (no diagonals)
                    const adjacentCows = this.cows.filter(cow => {
                        const dx = Math.abs(cow.x - this.dog.x);
                        const dy = Math.abs(cow.y - this.dog.y);
                        return (dx === 1 && dy === 0) || (dx === 0 && dy === 1);
                    });



                    for (const cow of adjacentCows) {

                        await this.moveCowAwayFromDog(cow);
                    }

                    await this.sleep(300); // Animate each step
                } else {
                    break;
                }
            }
        },

        async handleCowReactions() {
            await this.sleep(500);

            // ALL cows move during this phase, regardless of whether they reacted earlier
            for (const cow of this.cows) {
                await this.moveCowRandomly(cow);
            }

            this.updateCounters();
        },

        async moveCowAwayFromDog(cow) {


            // Calculate direction away from dog
            const dx = cow.x - this.dog.x;
            const dy = cow.y - this.dog.y;


            // For cows in same space as dog, move to any valid orthogonal space
            if (dx === 0 && dy === 0) {

                const directions = [
                    { x: 0, y: 1 }, { x: 1, y: 0 }, { x: 0, y: -1 }, { x: -1, y: 0 }
                ];

                // Shuffle directions for randomness
                for (let i = directions.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [directions[i], directions[j]] = [directions[j], directions[i]];
                }

                for (const dir of directions) {
                    const newX = cow.x + dir.x;
                    const newY = cow.y + dir.y;

                    if (this.isValidCowMove(newX, newY)) {
                        await this.pushCowToPosition(cow, newX, newY);
                        return;
                    }
                }

                return;
            }

            // For orthogonally adjacent cows, try to move away (with pushing)
            if (Math.abs(dx) === 1 && dy === 0) {
                // Cow is directly left or right of dog
                const moveX = dx > 0 ? 1 : -1; // Move further away horizontally
                const newX = cow.x + moveX;
                const newY = cow.y;

                await this.pushCowToPosition(cow, newX, newY);
                return;
            }

            if (Math.abs(dy) === 1 && dx === 0) {
                // Cow is directly above or below dog
                const moveY = dy > 0 ? 1 : -1; // Move further away vertically
                const newX = cow.x;
                const newY = cow.y + moveY;

                await this.pushCowToPosition(cow, newX, newY);
                return;
            }

            // If not adjacent, try fallback directions
            if (Math.abs(dx) === 1 || Math.abs(dy) === 1) {
                const fallbackDirections = [
                    { x: 0, y: 1 }, { x: 1, y: 0 }, { x: 0, y: -1 }, { x: -1, y: 0 }
                ];

                for (const dir of fallbackDirections) {
                    const newX = cow.x + dir.x;
                    const newY = cow.y + dir.y;

                    if (this.isValidCowMove(newX, newY)) {
                        await this.pushCowToPosition(cow, newX, newY);
                        return;
                    }
                }
            }

        },

        async pushCowToPosition(cow, newX, newY) {

            // Check if destination is valid (not dog space, not fenced)
            if ((newX === this.dog.x && newY === this.dog.y) || this.hasFenceAt(newX, newY)) {

                return;
            }

            // Check if there's another cow in the destination
            const cowInDestination = this.cows.find(c => c.x === newX && c.y === newY && c.id !== cow.id);

            if (cowInDestination) {
                // Calculate push direction
                const pushX = newX - cow.x;
                const pushY = newY - cow.y;
                const chainNewX = newX + pushX;
                const chainNewY = newY + pushY;



                // Recursively push the cow in the destination
                await this.pushCowToPosition(cowInDestination, chainNewX, chainNewY);
            }

            // Now move the original cow (the space should be clear) with animation

            // Create new cow object to trigger reactivity
            const cowIndex = this.cows.findIndex(c => c.id === cow.id);
            if (cowIndex !== -1) {
                this.cows[cowIndex] = { ...cow, x: newX, y: newY };
                await this.sleep(200);
                await this.checkCowScoringAndLoss(this.cows[cowIndex]);
            }
        },

        async moveCowRandomly(cow) {
            const directions = [
                { x: 0, y: -1 }, { x: 1, y: 0 }, { x: 0, y: 1 }, { x: -1, y: 0 }
            ];

            // Calculate current distance from cow to dog
            const currentDistance = Math.abs(this.dog.x - cow.x) + Math.abs(this.dog.y - cow.y);

            const validMoves = directions.filter(dir => {
                const newX = cow.x + dir.x;
                const newY = cow.y + dir.y;

                // Calculate new distance from potential position to dog
                const newDistance = Math.abs(this.dog.x - newX) + Math.abs(this.dog.y - newY);

                return this.isValidCowMove(newX, newY) &&
                       newDistance >= currentDistance; // Never get closer to dog
            });

            if (validMoves.length > 0 && Math.random() < 0.7) { // 70% chance to move
                const move = validMoves[Math.floor(Math.random() * validMoves.length)];
                const newX = cow.x + move.x;
                const newY = cow.y + move.y;
                await this.pushCowToPosition(cow, newX, newY);
            }
        },

        isValidCowMove(x, y) {
            // Cows can move off-grid or into pen, but NEVER into dog's space or fenced areas
            return !(x === this.dog.x && y === this.dog.y) &&
                   !this.hasFenceAt(x, y);
        },

        isOrthogonallyAdjacentToDog(x, y) {
            const dx = Math.abs(x - this.dog.x);
            const dy = Math.abs(y - this.dog.y);
            return (dx === 1 && dy === 0) || (dx === 0 && dy === 1);
        },

        async checkCowScoringAndLoss(cow) {
            // Check if cow moved into pen (score and remove)
            if (this.isPen(cow.x, cow.y)) {
                this.cowsScored++;
                const cowIndex = this.cows.indexOf(cow);
                if (cowIndex > -1) {
                    this.cows.splice(cowIndex, 1);
                }
                this.cowsRemaining = this.cows.length;
                return;
            }

            // Check if cow moved off map (count as lost and remove)
            if (cow.x < 0 || cow.x >= this.gridWidth || cow.y < 0 || cow.y >= this.gridHeight) {
                this.cowsLost++;
                const cowIndex = this.cows.indexOf(cow);
                if (cowIndex > -1) {
                    this.cows.splice(cowIndex, 1);
                }
                this.cowsRemaining = this.cows.length;
            }
        },

        updateCounters() {
            // Update remaining count (scoring and loss handled immediately in checkCowScoringAndLoss)
            this.cowsRemaining = this.cows.length;
        },

        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
    }
}


</script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div x-data="gameData()" x-init="initGame(); console.log('Alpine initialized!', $data)" style="padding: 24px; background-color: #f0f9ff; min-height: 100vh; font-family: Arial, sans-serif;">
    <!-- Game Header -->
    <div style="margin-bottom: 12px; text-align: center;">
        <h1 style="font-size: 1.5rem; font-weight: bold; color: #1f2937; margin-bottom: 8px;">Herding Dog Game</h1>
        <div style="display: flex; justify-content: center; gap: 16px; font-size: 0.9rem; font-weight: 600;">
            <div style="background-color: #3b82f6; color: white; padding: 4px 8px; border-radius: 4px;">
                Remaining: <span x-text="cowsRemaining"></span>
            </div>
            <div style="background-color: #10b981; color: white; padding: 4px 8px; border-radius: 4px;">
                Scored: <span x-text="cowsScored"></span>
            </div>
            <div style="background-color: #ef4444; color: white; padding: 4px 8px; border-radius: 4px;">
                Lost: <span x-text="cowsLost"></span>
            </div>
        </div>
    </div>

    <!-- Game Grid -->
    <div style="margin-bottom: 12px; display: flex; justify-content: center;">
        <div style="position: relative; width: 532px; height: 532px; background-color: #8b4513; padding: 16px; border-radius: 8px; margin: 0 auto;">
            <!-- Grid Background -->
            <template x-for="y in 10" :key="`row-${y-1}`">
                <template x-for="x in 10" :key="`cell-${x-1}-${y-1}`">
                    <div
                        style="position: absolute; width: 48px; height: 48px; border: 1px solid #666; font-size: 1.5rem;"
                        :style="{
                            backgroundColor: isPen(x-1, y-1) ? '#ca8a04' : '#86efac',
                            left: ((x-1) * 50) + 'px',
                            top: ((10 - y) * 50) + 'px'
                        }"
                    >
                        <!-- Fences -->
                        <template x-for="fence in fences.filter(f => f.x === x-1 && f.y === y-1)" :key="`fence-${fence.x}-${fence.y}`">
                            <div style="position: absolute; z-index: 2; font-size: 1.8rem; top: 50%; left: 50%; transform: translate(-50%, -50%);">🚧</div>
                        </template>
                        <!-- Pen marker -->
                        <div x-show="isPen(x-1, y-1) && !hasCowAt(x-1, y-1) && !(dog.x === x-1 && dog.y === y-1) && !hasFenceAt(x-1, y-1)"
                             style="font-size: 10px; color: #451a03; z-index: 1; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">PEN</div>
                    </div>
                </template>
            </template>

            <!-- Dog (Animated) -->
            <div
                style="position: absolute; z-index: 3; transition: all 0.3s ease; width: 40px; height: 40px;"
                :style="{
                    left: (dog.x * 50 + 4) + 'px',
                    top: ((9 - dog.y) * 50 + 4) + 'px'
                }"
            >
                <img :src="`/images/${getDogIcon()}.png`" style="width: 40px; height: 40px;" :alt="`Dog facing ${getDogIcon()}`">
            </div>

            <!-- Cows (Animated) -->
            <template x-for="cow in cows" :key="cow.id">
                <div
                    style="position: absolute; z-index: 2; transition: all 0.2s ease; width: 32px; height: 32px;"
                    :style="{
                        left: (cow.x * 50 + 8) + 'px',
                        top: ((9 - cow.y) * 50 + 8) + 'px'
                    }"
                >
                    <img :src="`/images/${cow.color}.png`" style="width: 32px; height: 32px;" :alt="`${cow.color} cow`">
                </div>
            </template>
        </div>
    </div>

    <!-- Pivot Controls -->
    <div style="margin-bottom: 12px;">
        <h3 style="font-size: 1rem; font-weight: bold; margin-bottom: 6px; text-align: center;">Pivot Controls</h3>
        <div style="display: flex; justify-content: center; gap: 6px; margin-bottom: 8px; flex-wrap: wrap;">
            <div
                @click="addPivotToSequence('turnLeft')"
                :disabled="isPlaying"
                style="background-color: #e0f2fe; border: 2px solid #0284c7; border-radius: 6px; cursor: pointer; transition: all 0.2s; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;"
                :style="{
                    opacity: isPlaying ? '0.5' : '1',
                    cursor: isPlaying ? 'not-allowed' : 'pointer'
                }"
                @mouseover="if (!isPlaying) $el.style.backgroundColor = '#bae6fd'"
                @mouseout="if (!isPlaying) $el.style.backgroundColor = '#e0f2fe'"
            >
                <img src="/images/left.png" style="width: 48px; height: 48px;" alt="Turn Left">
            </div>
            <div
                @click="addPivotToSequence('turnRight')"
                :disabled="isPlaying"
                style="background-color: #e0f2fe; border: 2px solid #0284c7; border-radius: 6px; cursor: pointer; transition: all 0.2s; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;"
                :style="{
                    opacity: isPlaying ? '0.5' : '1',
                    cursor: isPlaying ? 'not-allowed' : 'pointer'
                }"
                @mouseover="if (!isPlaying) $el.style.backgroundColor = '#bae6fd'"
                @mouseout="if (!isPlaying) $el.style.backgroundColor = '#e0f2fe'"
            >
                <img src="/images/right.png" style="width: 48px; height: 48px;" alt="Turn Right">
            </div>
            <div
                @click="addPivotToSequence('turnAround')"
                :disabled="isPlaying"
                style="background-color: #e0f2fe; border: 2px solid #0284c7; border-radius: 6px; cursor: pointer; transition: all 0.2s; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;"
                :style="{
                    opacity: isPlaying ? '0.5' : '1',
                    cursor: isPlaying ? 'not-allowed' : 'pointer'
                }"
                @mouseover="if (!isPlaying) $el.style.backgroundColor = '#bae6fd'"
                @mouseout="if (!isPlaying) $el.style.backgroundColor = '#e0f2fe'"
            >
                <img src="/images/u.png" style="width: 48px; height: 48px;" alt="Turn Around">
            </div>
        </div>
    </div>

    <!-- Card Selection Area -->
    <div style="margin-bottom: 12px;">
        <h3 style="font-size: 1rem; font-weight: bold; margin-bottom: 6px; text-align: center;">Movement Cards</h3>
        <div style="display: flex; justify-content: center; gap: 6px; margin-bottom: 8px; flex-wrap: wrap;">
            <template x-for="(card, index) in hand" :key="`hand-${index}`">
                <div
                    @click="selectCard(index)"
                    style="background-color: white; border: 2px solid #d1d5db; border-radius: 6px; padding: 8px; cursor: pointer; transition: all 0.2s; min-width: 60px; text-align: center;"
                    :style="{
                        opacity: card.used ? '0.5' : '1',
                        backgroundColor: card.used ? '#f3f4f6' : 'white'
                    }"
                    @mouseover="$el.style.backgroundColor = card.used ? '#f3f4f6' : '#dbeafe'"
                    @mouseout="$el.style.backgroundColor = card.used ? '#f3f4f6' : 'white'"
                >
                    <div style="font-size: 1.5rem; margin-bottom: 4px;" x-text="card.icon"></div>
                    <div style="font-size: 11px; font-weight: 600;" x-text="card.name"></div>
                </div>
            </template>
        </div>
    </div>

    <!-- Selected Sequence -->
    <div style="margin-bottom: 12px;" x-show="selectedSequence.length > 0">
        <h3 style="font-size: 1rem; font-weight: bold; margin-bottom: 6px; text-align: center;">Selected Sequence</h3>
        <div style="display: flex; justify-content: center; gap: 6px; margin-bottom: 8px; flex-wrap: wrap;">
            <template x-for="(card, index) in selectedSequence" :key="`seq-${index}`">
                <div style="background-color: #dbeafe; border: 2px solid #3b82f6; border-radius: 6px; padding: 8px; position: relative; min-width: 60px; text-align: center;">
                    <div style="font-size: 1.2rem; margin-bottom: 2px;" x-text="card.icon"></div>
                    <div style="font-size: 10px; font-weight: 600;" x-text="card.name"></div>
                    <div
                        @click="removeFromSequence(index)"
                        style="position: absolute; top: -6px; right: -6px; background-color: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 10px; cursor: pointer; font-weight: bold;"
                        @mouseover="$el.style.backgroundColor = '#dc2626'"
                        @mouseout="$el.style.backgroundColor = '#ef4444'"
                    >×</div>
                    <div x-show="currentCardIndex === index && isPlaying"
                         style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255, 255, 0, 0.5); border-radius: 6px;"></div>
                </div>
            </template>
        </div>
        <div style="text-align: center;">
            <button
                @click="playSequence()"
                :disabled="isPlaying"
                style="background-color: #10b981; color: white; padding: 6px 16px; border-radius: 6px; font-weight: 600; border: none; cursor: pointer; margin-right: 8px; font-size: 14px;"
                :style="{
                    opacity: isPlaying ? '0.5' : '1',
                    cursor: isPlaying ? 'not-allowed' : 'pointer'
                }"
                @mouseover="if (!isPlaying) $el.style.backgroundColor = '#059669'"
                @mouseout="if (!isPlaying) $el.style.backgroundColor = '#10b981'"
            >
                <span x-show="!isPlaying">Play Sequence</span>
                <span x-show="isPlaying">Playing...</span>
            </button>
            <button
                @click="clearSequence()"
                :disabled="isPlaying"
                style="background-color: #ef4444; color: white; padding: 6px 16px; border-radius: 6px; font-weight: 600; border: none; cursor: pointer; font-size: 14px;"
                :style="{
                    opacity: isPlaying ? '0.5' : '1',
                    cursor: isPlaying ? 'not-allowed' : 'pointer'
                }"
                @mouseover="if (!isPlaying) $el.style.backgroundColor = '#dc2626'"
                @mouseout="if (!isPlaying) $el.style.backgroundColor = '#ef4444'"
            >
                Clear
            </button>
        </div>
    </div>

    <!-- Game Status -->
    <div style="text-align: center; color: #6b7280;" x-show="isPlaying">
        <p>Playing card <span x-text="currentCardIndex + 1"></span> of <span x-text="selectedSequence.length"></span></p>
    </div>
</div>
