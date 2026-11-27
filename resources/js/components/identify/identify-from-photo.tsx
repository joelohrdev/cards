import { Camera, ScanSearch } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useLookup } from "@/hooks/use-lookup";
import { shrinkPhoto } from "@/lib/shrink-photo";
import { cn } from "@/lib/utils";
import identify from "@/routes/identify";
import type { PhotoSuggestion } from "@/types";

/**
 * Longest edge sent to the vision model. The scaled-down photos are also what the
 * caller gets back to save as the card's front and back.
 */
const MAX_EDGE = 1568;

const NOT_CONFIGURED_MESSAGE =
    "Photo identification isn’t set up yet. Add an Anthropic API key as ANTHROPIC_API_KEY in the .env file to turn it on.";

function readCookie(name: string): string | null {
    const match = document.cookie
        .split("; ")
        .find((entry) => entry.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

/**
 * "Scan front & back": takes a photo of the card's front, then its back (which can be
 * skipped), asks the vision model what card it is, and hands back a suggestion with
 * likely catalog matches plus both photos.
 */
export function IdentifyFromPhoto({
    onResult,
}: {
    onResult: (
        suggestion: PhotoSuggestion,
        front: File,
        back: File | null,
    ) => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [front, setFront] = useState<File | null>(null);
    const [frontPreview, setFrontPreview] = useState<string | null>(null);
    const [working, setWorking] = useState(false);
    const [message, setMessage] = useState<{
        tone: "ok" | "error";
        text: string;
    } | null>(null);
    const { data: status } = useLookup<{ configured: boolean }>(
        identify.photo.show.url(),
        0,
    );
    const notConfigured = status !== null && !status.configured;

    useEffect(() => {
        if (!front) {
            setFrontPreview(null);

            return;
        }

        const url = URL.createObjectURL(front);
        setFrontPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [front]);

    function openCamera() {
        if (notConfigured) {
            setMessage({ tone: "error", text: NOT_CONFIGURED_MESSAGE });

            return;
        }

        input.current?.click();
    }

    async function photoTaken(file: File | undefined) {
        if (input.current) {
            input.current.value = "";
        }

        if (!file) {
            return;
        }

        setMessage(null);

        if (front === null) {
            setFront(await shrinkPhoto(file, MAX_EDGE));

            return;
        }

        await upload(front, file);
    }

    async function upload(frontPhoto: File, backFile: File | null) {
        setWorking(true);
        setMessage(null);

        try {
            const back = backFile
                ? await shrinkPhoto(backFile, MAX_EDGE)
                : null;
            const body = new FormData();
            body.append("photo", frontPhoto, frontPhoto.name);

            if (back) {
                body.append("back_photo", back, back.name);
            }

            const response = await fetch(identify.photo.store.url(), {
                method: "POST",
                body,
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-XSRF-TOKEN": readCookie("XSRF-TOKEN") ?? "",
                },
            });
            const json = (await response.json().catch(() => ({}))) as {
                suggestion?: PhotoSuggestion;
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (response.ok && json.suggestion) {
                onResult(json.suggestion, frontPhoto, back);
                setFront(null);
                setMessage({
                    tone: "ok",
                    text:
                        json.suggestion.matches.length > 0
                            ? "Filled in what we could read. Check the details before saving."
                            : "Filled in what we could read, but found no matching card in the catalog. Check the details before saving.",
                });

                return;
            }

            if (response.status === 429) {
                setMessage({
                    tone: "error",
                    text: "That’s a lot of photos in a row. Wait a minute, then try again.",
                });

                return;
            }

            const firstError = json.errors
                ? Object.values(json.errors)[0]?.[0]
                : undefined;
            setMessage({
                tone: "error",
                text:
                    firstError ??
                    json.message ??
                    "Couldn’t identify those photos. Try again, or enter the card by hand.",
            });
        } catch {
            setMessage({
                tone: "error",
                text: "Couldn’t reach the server. Check your connection and try again.",
            });
        } finally {
            setWorking(false);
        }
    }

    return (
        <div className="grid gap-1.5">
            {front === null ? (
                <Button type="button" variant="outline" onClick={openCamera}>
                    <ScanSearch />
                    Scan front &amp; back
                </Button>
            ) : (
                <div className="flex items-center gap-3 rounded-md border bg-muted/40 p-2">
                    {frontPreview && (
                        <img
                            src={frontPreview}
                            alt="Front of the card"
                            className="aspect-[5/7] w-12 shrink-0 rounded-sm object-cover"
                        />
                    )}
                    <div className="grid gap-2">
                        <p className="text-sm">
                            {working
                                ? "Reading the card…"
                                : "Got the front. Now flip the card over."}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                onClick={openCamera}
                                disabled={working}
                            >
                                {working ? <Spinner /> : <Camera />}
                                Take the back
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => upload(front, null)}
                                disabled={working}
                            >
                                Skip the back
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                    setFront(null);
                                    setMessage(null);
                                }}
                                disabled={working}
                            >
                                Start over
                            </Button>
                        </div>
                    </div>
                </div>
            )}
            <input
                ref={input}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={(event) => photoTaken(event.target.files?.[0])}
            />
            {message && (
                <p
                    className={cn(
                        "text-xs",
                        message.tone === "error"
                            ? "text-destructive"
                            : "text-muted-foreground",
                    )}
                    role="status"
                >
                    {message.text}
                </p>
            )}
        </div>
    );
}
