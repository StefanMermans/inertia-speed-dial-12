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
    const [date, setDate] = useState<Date|null>(null);

    useEffect(() => {
        function updateDate() {
            setDate((prevDate) => {
                const newDate = new Date();
    
                if (newDate.getUTCMinutes() === prevDate?.getUTCMinutes()) {
                    return prevDate;
                }
    
                return newDate;
            });
        }

        const intervalId = setInterval(updateDate, 1000);
        updateDate();

        return () => {
            clearInterval(intervalId);
        };
    }, []);

    if (!date) {
        return null;
    }

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
