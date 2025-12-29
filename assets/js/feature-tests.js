/**
 * Feature Tests
 * Test các tính năng mới để đảm bảo hoạt động đúng
 */

const FeatureTests = {
    // Test Quick Actions
    testQuickActions: function() {
        console.log('🧪 Testing Quick Actions...');
        const container = document.getElementById('quickActionsContainer');
        if (!container) {
            console.error('❌ Quick Actions container not found');
            return false;
        }
        
        const cards = container.querySelectorAll('.quick-action-card');
        if (cards.length === 0) {
            console.error('❌ No quick action cards found');
            return false;
        }
        
        console.log(`✅ Quick Actions: ${cards.length} cards found`);
        return true;
    },
    
    // Test Dashboard Widgets
    testDashboardWidgets: function() {
        console.log('🧪 Testing Dashboard Widgets...');
        
        // Test live clock
        const clock = document.getElementById('liveTime');
        if (clock && clock.textContent !== '--:--:--') {
            console.log('✅ Live Clock: Working');
        } else {
            console.error('❌ Live Clock: Not working');
        }
        
        // Test stats
        const stats = document.querySelectorAll('.stat-value[data-target]');
        if (stats.length > 0) {
            console.log(`✅ Stats: ${stats.length} stat cards found`);
        } else {
            console.error('❌ Stats: No stat cards found');
        }
        
        return true;
    },
    
    // Test Search
    testQuickSearch: function() {
        console.log('🧪 Testing Quick Search...');
        
        if (typeof QuickActions !== 'undefined' && QuickActions.openQuickSearch) {
            console.log('✅ Quick Search: Function available');
            return true;
        } else {
            console.error('❌ Quick Search: Function not available');
            return false;
        }
    },
    
    // Test Offline Detection
    testOfflineDetection: function() {
        console.log('🧪 Testing Offline Detection...');
        
        if (typeof OfflineDetector !== 'undefined') {
            console.log('✅ Offline Detector: Available');
            console.log(`   Online status: ${OfflineDetector.isOnline()}`);
            return true;
        } else {
            console.error('❌ Offline Detector: Not available');
            return false;
        }
    },
    
    // Test Notifications
    testNotifications: function() {
        console.log('🧪 Testing Notifications...');
        
        if (typeof NotificationsEnhancer !== 'undefined') {
            console.log('✅ Notifications Enhancer: Available');
            return true;
        } else {
            console.error('❌ Notifications Enhancer: Not available');
            return false;
        }
    },
    
    // Test Performance Optimizer
    testPerformanceOptimizer: function() {
        console.log('🧪 Testing Performance Optimizer...');
        
        if (typeof PerformanceOptimizer !== 'undefined') {
            console.log('✅ Performance Optimizer: Available');
            console.log('   Debounce function:', typeof PerformanceOptimizer.debounce);
            console.log('   Throttle function:', typeof PerformanceOptimizer.throttle);
            return true;
        } else {
            console.error('❌ Performance Optimizer: Not available');
            return false;
        }
    },
    
    // Test Theme Preview
    testThemePreview: function() {
        console.log('🧪 Testing Theme Preview...');
        
        if (typeof ThemePreview !== 'undefined') {
            console.log('✅ Theme Preview: Available');
            return true;
        } else {
            console.error('❌ Theme Preview: Not available');
            return false;
        }
    },
    
    // Test Copy to Clipboard
    testCopyToClipboard: function() {
        console.log('🧪 Testing Copy to Clipboard...');
        
        if (typeof QuickActions !== 'undefined' && QuickActions.copyToClipboard) {
            console.log('✅ Copy to Clipboard: Available');
            return true;
        } else {
            console.error('❌ Copy to Clipboard: Not available');
            return false;
        }
    },
    
    // Run all tests
    runAllTests: function() {
        console.log('🚀 Running all feature tests...\n');
        
        const results = {
            quickActions: this.testQuickActions(),
            dashboardWidgets: this.testDashboardWidgets(),
            quickSearch: this.testQuickSearch(),
            offlineDetection: this.testOfflineDetection(),
            notifications: this.testNotifications(),
            performanceOptimizer: this.testPerformanceOptimizer(),
            themePreview: this.testThemePreview(),
            copyToClipboard: this.testCopyToClipboard()
        };
        
        const passed = Object.values(results).filter(r => r).length;
        const total = Object.keys(results).length;
        
        console.log(`\n📊 Test Results: ${passed}/${total} passed`);
        
        if (passed === total) {
            console.log('✅ All tests passed!');
        } else {
            console.log('⚠️ Some tests failed. Check console for details.');
        }
        
        return results;
    }
};

// Auto-run tests in development
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            FeatureTests.runAllTests();
        }, 2000); // Wait for all scripts to load
    });
}

// Export for manual testing
window.FeatureTests = FeatureTests;

