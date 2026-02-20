import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const key = import.meta.env.VITE_REVERB_APP_KEY;
const host = import.meta.env.VITE_REVERB_HOST;

function createNoOpEcho(): typeof Echo {
    const noOpChannel = {
        listen: (_event: string, _cb: (data: unknown) => void) => noOpChannel,
    };
    return {
        connector: undefined,
        private: (_name: string) => noOpChannel,
        leave: (_name: string) => {},
    } as unknown as typeof Echo;
}

const echo =
    key && host
        ? (() => {
              window.Pusher = Pusher;
              return new Echo({
                  broadcaster: 'reverb',
                  key,
                  wsHost: host,
                  wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
                  wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
                  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                  enabledTransports: ['ws', 'wss'],
              });
          })()
        : createNoOpEcho();

export default echo;
