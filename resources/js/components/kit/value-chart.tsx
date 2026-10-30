import { useMemo, useRef, useState } from 'react';
import { money, shortDate } from '@/lib/format';

type Point = { date: string; value: number; cost: number };

const WIDTH = 720;
const HEIGHT = 220;
const PAD = { top: 12, right: 12, bottom: 24, left: 12 };

/**
 * Collection value vs. cost basis over time, with a crosshair tooltip.
 */
export function ValueChart({ points }: { points: Point[] }) {
    const svg = useRef<SVGSVGElement>(null);
    const [hover, setHover] = useState<number | null>(null);

    const geometry = useMemo(() => {
        const max = Math.max(
            1,
            ...points.flatMap((point) => [point.value, point.cost]),
        );
        const innerWidth = WIDTH - PAD.left - PAD.right;
        const innerHeight = HEIGHT - PAD.top - PAD.bottom;
        const x = (index: number) =>
            PAD.left +
            (points.length === 1
                ? innerWidth
                : (index / (points.length - 1)) * innerWidth);
        const y = (value: number) =>
            PAD.top + innerHeight - (value / max) * innerHeight;
        const line = (key: 'value' | 'cost') =>
            points
                .map(
                    (point, index) =>
                        `${index === 0 ? 'M' : 'L'}${x(index)},${y(point[key])}`,
                )
                .join(' ');

        return {
            x,
            y,
            valueLine: line('value'),
            costLine: line('cost'),
            area: `${line('value')} L${x(points.length - 1)},${PAD.top + innerHeight} L${x(0)},${PAD.top + innerHeight} Z`,
            baseline: PAD.top + innerHeight,
        };
    }, [points]);

    if (points.length < 2) {
        return (
            <p className="py-10 text-center text-sm text-muted-foreground">
                The value chart fills in as daily snapshots are recorded. Check
                back tomorrow.
            </p>
        );
    }

    function onMove(event: React.PointerEvent<SVGSVGElement>) {
        const rect = svg.current?.getBoundingClientRect();

        if (!rect) {
            return;
        }

        const ratio = (event.clientX - rect.left) / rect.width;
        setHover(
            Math.max(
                0,
                Math.min(
                    points.length - 1,
                    Math.round(ratio * (points.length - 1)),
                ),
            ),
        );
    }

    const active = hover !== null ? points[hover] : null;

    return (
        <div className="relative">
            <div className="mb-3 flex items-center gap-4 text-xs text-muted-foreground">
                <span className="flex items-center gap-1.5">
                    <span className="h-0.5 w-4 rounded bg-chart-1" /> Estimated
                    value
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="h-0 w-4 border-t-2 border-dashed border-muted-foreground" />{' '}
                    Cost basis
                </span>
            </div>
            <svg
                ref={svg}
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="h-auto w-full touch-none"
                role="img"
                aria-label="Collection value over time"
                onPointerMove={onMove}
                onPointerLeave={() => setHover(null)}
            >
                <line
                    x1={PAD.left}
                    x2={WIDTH - PAD.right}
                    y1={geometry.baseline}
                    y2={geometry.baseline}
                    className="stroke-border"
                />
                <path d={geometry.area} className="fill-chart-1/10" />
                <path
                    d={geometry.costLine}
                    fill="none"
                    strokeWidth={2}
                    strokeDasharray="5 4"
                    className="stroke-muted-foreground"
                />
                <path
                    d={geometry.valueLine}
                    fill="none"
                    strokeWidth={2}
                    strokeLinejoin="round"
                    className="stroke-chart-1"
                />
                <text
                    x={PAD.left}
                    y={HEIGHT - 6}
                    className="fill-muted-foreground text-[11px]"
                >
                    {shortDate(points[0].date)}
                </text>
                <text
                    x={WIDTH - PAD.right}
                    y={HEIGHT - 6}
                    textAnchor="end"
                    className="fill-muted-foreground text-[11px]"
                >
                    {shortDate(points[points.length - 1].date)}
                </text>
                {active && hover !== null && (
                    <g>
                        <line
                            x1={geometry.x(hover)}
                            x2={geometry.x(hover)}
                            y1={PAD.top}
                            y2={geometry.baseline}
                            className="stroke-foreground/30"
                        />
                        <circle
                            cx={geometry.x(hover)}
                            cy={geometry.y(active.value)}
                            r={5}
                            strokeWidth={2}
                            className="fill-chart-1 stroke-card"
                        />
                    </g>
                )}
            </svg>
            {active && hover !== null && (
                <div
                    className="pointer-events-none absolute top-8 rounded-md border bg-popover px-3 py-2 text-xs shadow-md"
                    style={{
                        left: `${(geometry.x(hover) / WIDTH) * 100}%`,
                        transform:
                            hover > points.length / 2
                                ? 'translateX(calc(-100% - 12px))'
                                : 'translateX(12px)',
                    }}
                >
                    <p className="font-medium">{shortDate(active.date)}</p>
                    <p className="tabular">Value {money(active.value)}</p>
                    <p className="text-muted-foreground tabular">
                        Cost {money(active.cost)}
                    </p>
                </div>
            )}
        </div>
    );
}
