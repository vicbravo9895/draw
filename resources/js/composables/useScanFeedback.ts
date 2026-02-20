let audioCtx: AudioContext | null = null;

function getAudioContext(): AudioContext | null {
    try {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
        }
        return audioCtx;
    } catch {
        return null;
    }
}

/**
 * Play a short success beep using the Web Audio API.
 */
function playBeep(frequency = 880, durationMs = 120) {
    const ctx = getAudioContext();
    if (!ctx) return;

    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();
    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(frequency, ctx.currentTime);
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + durationMs / 1000);
    oscillator.connect(gain);
    gain.connect(ctx.destination);
    oscillator.start(ctx.currentTime);
    oscillator.stop(ctx.currentTime + durationMs / 1000);
}

/**
 * Trigger a short vibration (if supported by the device).
 */
function vibrate(pattern: number | number[] = 50) {
    if (navigator.vibrate) {
        navigator.vibrate(pattern);
    }
}

/**
 * Composable providing haptic/audio feedback for barcode scans and save actions.
 */
export function useScanFeedback() {
    function onScanSuccess() {
        vibrate(50);
        playBeep(880, 120);
    }

    function onSaveSuccess() {
        vibrate([30, 50, 30]);
        playBeep(1100, 80);
    }

    function onError() {
        vibrate([100, 30, 100]);
        playBeep(300, 200);
    }

    return {
        playBeep,
        vibrate,
        onScanSuccess,
        onSaveSuccess,
        onError,
    };
}
