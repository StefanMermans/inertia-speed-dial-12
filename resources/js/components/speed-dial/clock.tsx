import { useEffect, useState } from 'react';

const TimeFormatter = Intl.DateTimeFormat('nl', {
    timeStyle: 'short',
});

const DateFormatter = Intl.DateTimeFormat('nl', {
    month: 'long',
    day: 'numeric',
    weekday: 'long',
});

export default function Clock() {
    const [date, setDate] = useState<Date>(new Date());

    useEffect(() => {
        const intervalId = setInterval(() => {
            setDate((date) => {
                const newDate = new Date();

                if (newDate.getUTCMinutes() === date.getUTCMinutes()) {
                    return date;
                }

                return newDate;
            });
        }, 1000);

        return () => {
            clearInterval(intervalId);
        };
    }, []);

    return (
        <div className="flex flex-shrink-0 flex-col justify-end text-white">
            <time className="text-9xl font-thin">
                {date && TimeFormatter.format(date)}
            </time>
            <time className="text-4xl font-light">
                {date && DateFormatter.format(date)}
            </time>
        </div>
    );
}
