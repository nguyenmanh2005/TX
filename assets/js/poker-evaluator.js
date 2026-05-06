/**
 * Poker Hand Evaluator
 * Hand Ranks: 0 (High Card) to 9 (Royal Flush)
 */

class PokerEvaluator {
  static SUITS = ['♠', '♥', '♦', '♣'];
  static VALUES = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
  static NUMERIC_VALUES = {
    '2': 2, '3': 3, '4': 4, '5': 5, '6': 6, '7': 7, '8': 8, '9': 9, '10': 10,
    'J': 11, 'Q': 12, 'K': 13, 'A': 14
  };

  static RANKS = {
    ROYAL_FLUSH: 9,
    STRAIGHT_FLUSH: 8,
    FOUR_OF_A_KIND: 7,
    FULL_HOUSE: 6,
    FLUSH: 5,
    STRAIGHT: 4,
    THREE_OF_A_KIND: 3,
    TWO_PAIR: 2,
    PAIR: 1,
    HIGH_CARD: 0
  };

  static RANK_NAMES = [
    "High Card", "Pair", "Two Pair", "Three of a Kind", 
    "Straight", "Flush", "Full House", "Four of a Kind", 
    "Straight Flush", "Royal Flush"
  ];

  /**
   * Evaluates the best 5-card hand from a set of up to 7 cards.
   * @param {Array} cards - Array of card objects {suit, value, numericValue}
   */
  static evaluateHand(cards) {
    if (cards.length < 5) return { rank: -1, description: "Wait for more cards" };

    // Sort cards by value descending
    const sorted = [...cards].sort((a, b) => b.numericValue - a.numericValue);
    
    // Check in order of strength
    const flushResult = this.getFlush(sorted);
    const straightResult = this.getStraight(sorted);
    
    // Check Straight Flush / Royal Flush
    if (flushResult && straightResult) {
      // Need to verify if the straight is within the flush cards
      const flushCards = sorted.filter(c => c.suit === flushResult.suit);
      const straightFlush = this.getStraight(flushCards);
      if (straightFlush) {
        if (straightFlush.highCard === 14) return { rank: 9, score: 900 + 14, description: "Royal Flush" };
        return { rank: 8, score: 800 + straightFlush.highCard, description: "Straight Flush" };
      }
    }

    // N-of-a-kind checks
    const counts = this.getValueCounts(sorted);
    const pairs = counts.filter(c => c.count === 2);
    const trips = counts.filter(c => c.count === 3);
    const quads = counts.filter(c => c.count === 4);

    if (quads.length > 0) {
      return { rank: 7, score: 700 + quads[0].value, description: "Four of a Kind" };
    }

    if (trips.length > 0 && (trips.length > 1 || pairs.length > 0)) {
      const tripVal = trips[0].value;
      const pairVal = trips.length > 1 ? trips[1].value : pairs[0].value;
      return { rank: 6, score: 600 + tripVal, description: `Full House (${tripVal}s and ${pairVal}s)` };
    }

    if (flushResult) {
      return { rank: 5, score: 500 + flushResult.highCard, description: "Flush" };
    }

    if (straightResult) {
      return { rank: 4, score: 400 + straightResult.highCard, description: "Straight" };
    }

    if (trips.length > 0) {
      const kicker1 = sorted.find(c => c.numericValue !== trips[0].value);
      return { rank: 3, score: 300 + trips[0].value + (kicker1 ? kicker1.numericValue / 100 : 0), description: "Three of a Kind" };
    }

    if (pairs.length >= 2) {
      const kicker = sorted.find(c => c.numericValue !== pairs[0].value && c.numericValue !== pairs[1].value);
      return { rank: 2, score: 200 + pairs[0].value + (pairs[1].value / 100) + (kicker ? kicker.numericValue / 1000 : 0), description: `Two Pair (${pairs[0].name} & ${pairs[1].name})` };
    }

    if (pairs.length === 1) {
      const remaining = sorted.filter(c => c.numericValue !== pairs[0].value).slice(0, 3);
      const kickerScore = remaining.reduce((acc, c, i) => acc + (c.numericValue / Math.pow(10, i + 1)), 0);
      return { rank: 1, score: 100 + pairs[0].value + (kickerScore / 10), description: `Pair of ${pairs[0].name}s` };
    }

    const kickerScore = sorted.slice(0, 5).reduce((acc, c, i) => acc + (c.numericValue / Math.pow(10, i + 1)), 0);
    return { rank: 0, score: kickerScore, description: `High Card ${sorted[0].value}` };
  }

  static getValueCounts(cards) {
    const counts = {};
    cards.forEach(c => {
      counts[c.numericValue] = (counts[c.numericValue] || 0) + 1;
    });
    return Object.entries(counts)
      .map(([value, count]) => ({ 
        value: parseInt(value), 
        count, 
        name: cards.find(c => c.numericValue == value).value 
      }))
      .sort((a, b) => b.count !== a.count ? b.count - a.count : b.value - a.value);
  }

  static getFlush(cards) {
    const suits = {};
    cards.forEach(c => suits[c.suit] = (suits[c.suit] || 0) + 1);
    const flushSuit = Object.keys(suits).find(s => suits[s] >= 5);
    if (flushSuit) {
      const flushCards = cards.filter(c => c.suit === flushSuit);
      return { suit: flushSuit, highCard: flushCards[0].numericValue };
    }
    return null;
  }

  static getStraight(cards) {
    const values = [...new Set(cards.map(c => c.numericValue))].sort((a, b) => b - a);
    
    // Check for A-2-3-4-5 (Ace low straight)
    if (values.includes(14)) values.push(1); // Add Ace as 1
    
    let consecutive = 1;
    let highCard = -1;
    
    for (let i = 0; i < values.length - 1; i++) {
      if (values[i] === values[i + 1] + 1) {
        consecutive++;
        if (consecutive >= 5) {
          highCard = values[i - 3]; // The original start of the 5-card sequence
          return { highCard };
        }
      } else {
        consecutive = 1;
      }
    }
    return null;
  }
}
