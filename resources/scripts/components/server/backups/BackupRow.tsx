import React, { useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArchive, faEllipsisH, faLock } from '@fortawesome/free-solid-svg-icons';
import { format, formatDistanceToNow } from 'date-fns';
import { bytesToString } from '@/lib/formatters';
import Can from '@/components/elements/Can';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import BackupContextMenu from '@/components/server/backups/BackupContextMenu';
import tw from 'twin.macro';
import GreyRowBox from '@/components/elements/GreyRowBox';
import getServerBackups from '@/api/swr/getServerBackups';
import { ServerBackup } from '@/api/server/types';
import { SocketEvent } from '@/components/server/events';

interface Props {
    backup: ServerBackup;
    className?: string;
}

const progressRingProps = {
    cx: 16,
    cy: 16,
    r: 14,
    strokeWidth: 3,
    fill: 'none',
    stroke: 'currentColor',
};

const ProgressRing = ({ progress }: { progress: number }) => (
    <svg viewBox={'0 0 32 32'} css={tw`w-6 h-6 text-neutral-500`}>
        <circle {...progressRingProps} css={tw`opacity-25`} />
        <circle
            {...progressRingProps}
            stroke={'currentColor'}
            strokeDasharray={28 * Math.PI}
            css={tw`text-cyan-400 origin-center -rotate-90 transition-all duration-300`}
            style={{ strokeDashoffset: ((100 - Math.min(100, Math.max(0, progress))) / 100) * 28 * Math.PI }}
        />
    </svg>
);

const progressFromMessage = (message: string): { percent: number; label: string } => {
    const lower = message.toLowerCase();

    if (lower.includes('upload') || lower.includes('checksum') || lower.includes('s3') || lower.includes('complete')) {
        return { percent: 85, label: message };
    }

    if (lower.includes('archive') || lower.includes('creating') || lower.includes('compress')) {
        return { percent: 45, label: message };
    }

    return { percent: 15, label: message || 'Starting…' };
};

export default ({ backup, className }: Props) => {
    const { mutate } = getServerBackups();
    const [progress, setProgress] = useState<{ percent: number; label: string }>({
        percent: 5,
        label: 'Starting…',
    });

    useWebsocketEvent(SocketEvent.BACKUP_PROGRESS, (data) => {
        if (backup.completedAt !== null) {
            return;
        }

        try {
            const message = typeof data === 'string' ? data : String(data ?? '');
            setProgress(progressFromMessage(message));
        } catch (e) {
            console.warn(e);
        }
    });

    useWebsocketEvent(`${SocketEvent.BACKUP_COMPLETED}:${backup.uuid}` as SocketEvent, (data) => {
        try {
            const parsed = JSON.parse(data);
            const fileSize = parsed.file_size || 0;
            const failureReason =
                parsed.is_successful === false
                    ? parsed.failure_reason || 'Backup failed to complete.'
                    : null;

            setProgress({ percent: 100, label: 'Completed' });

            mutate(
                (data) => ({
                    ...data,
                    items: data.items.map((b) =>
                        b.uuid !== backup.uuid
                            ? b
                            : {
                                  ...b,
                                  isSuccessful: parsed.is_successful === undefined ? true : parsed.is_successful,
                                  checksum: (parsed.checksum_type || '') + ':' + (parsed.checksum || ''),
                                  bytes: fileSize,
                                  failureReason,
                                  completedAt: new Date(),
                              }
                    ),
                }),
                false
            );
        } catch (e) {
            console.warn(e);
        }
    });

    return (
        <GreyRowBox css={tw`flex-wrap md:flex-nowrap items-center`} className={className}>
            <div css={tw`flex items-center truncate w-full md:flex-1`}>
                <div css={tw`mr-4`}>
                    {backup.completedAt !== null ? (
                        backup.isLocked ? (
                            <FontAwesomeIcon icon={faLock} css={tw`text-yellow-500`} />
                        ) : (
                            <FontAwesomeIcon icon={faArchive} css={tw`text-neutral-300`} />
                        )
                    ) : (
                        <ProgressRing progress={progress.percent} />
                    )}
                </div>
                <div css={tw`flex flex-col truncate`}>
                    <div css={tw`flex items-center text-sm mb-1`}>
                        {backup.completedAt !== null && !backup.isSuccessful && (
                            <span
                                css={tw`bg-red-500 py-px px-2 rounded-full text-white text-xs uppercase border border-red-600 mr-2`}
                            >
                                Failed
                            </span>
                        )}
                        <p css={tw`break-words truncate`}>{backup.name}</p>
                        {backup.completedAt !== null && backup.isSuccessful && (
                            <span css={tw`ml-3 text-neutral-300 text-xs font-extralight hidden sm:inline`}>
                                {bytesToString(backup.bytes)}
                            </span>
                        )}
                        {backup.completedAt === null && (
                            <span css={tw`ml-3 text-cyan-400 text-xs font-medium`}>{Math.round(progress.percent)}%</span>
                        )}
                    </div>
                    {backup.completedAt === null ? (
                        <p css={tw`mt-1 md:mt-0 text-xs text-neutral-400 truncate`}>{progress.label}</p>
                    ) : (
                        <p css={tw`mt-1 md:mt-0 text-xs text-neutral-400 font-mono truncate`}>{backup.checksum}</p>
                    )}
                    {backup.completedAt !== null && !backup.isSuccessful && backup.failureReason && (
                        <p css={tw`mt-1 text-xs text-red-300 truncate`}>{backup.failureReason}</p>
                    )}
                </div>
            </div>
            <div css={tw`flex-1 md:flex-none md:w-48 mt-4 md:mt-0 md:ml-8 md:text-center`}>
                <p title={format(backup.createdAt, 'ddd, MMMM do, yyyy HH:mm:ss')} css={tw`text-sm`}>
                    {formatDistanceToNow(backup.createdAt, { includeSeconds: true, addSuffix: true })}
                </p>
                <p css={tw`text-2xs text-neutral-500 uppercase mt-1`}>Created</p>
            </div>
            <Can action={['backup.download', 'backup.restore', 'backup.delete']} matchAny>
                <div css={tw`mt-4 md:mt-0 ml-6`} style={{ marginRight: '-0.5rem' }}>
                    {!backup.completedAt ? (
                        <div css={tw`p-2 invisible`}>
                            <FontAwesomeIcon icon={faEllipsisH} />
                        </div>
                    ) : (
                        <BackupContextMenu backup={backup} />
                    )}
                </div>
            </Can>
        </GreyRowBox>
    );
};
