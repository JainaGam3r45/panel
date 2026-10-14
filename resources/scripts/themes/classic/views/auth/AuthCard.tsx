import type { ReactNode } from 'react';
import { cn, Form } from '@/sdk';

interface Props {
    title: string;
    submit: () => void;
    className?: string;
    children: ReactNode;
}

/** The sign-in card: the form beside the mark, under a title and above the copyright. */
export default function AuthCard({ title, submit, className, children }: Props) {
    return (
        <div className={cn('sm:mx-auto sm:w-4/5 md:p-10 lg:w-3/5 xl:w-full xl:max-w-auth', className)}>
            <h2 className='text-3xl text-center text-foreground font-medium py-4'>{title}</h2>
            <Form form={{ handleSubmit: submit }} className='w-full flex'>
                <div className='md:flex w-full bg-card text-card-foreground shadow-lg rounded-lg p-6 md:pl-0 mx-1'>
                    <div className='flex-none select-none mb-6 md:mb-0 self-center'>
                        <img
                            src='/favicons/android-chrome-512x512.png'
                            alt='JiferCloud'
                            className='block w-40 md:w-52 h-auto mx-auto rounded-lg'
                        />
                    </div>
                    <div className='flex-1'>{children}</div>
                </div>
            </Form>
            <p className='text-center text-muted-foreground text-xs mt-4'>
                <a
                    rel='noopener noreferrer'
                    href='https://www.jifercraft.com/'
                    target='_blank'
                    className='no-underline text-muted-foreground hover:text-muted-foreground'
                >
                    JiferCloud Hosting&reg;
                </a>
                &nbsp;&copy; 2020 - <span suppressHydrationWarning>{new Date().getFullYear()}</span>
                <br />
                Expansión de JiferCraft Studios
            </p>
        </div>
    );
}

export const authLinkClass = 'text-xs text-muted-foreground tracking-wide uppercase no-underline hover:text-foreground';
